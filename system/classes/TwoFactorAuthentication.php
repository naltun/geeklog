<?php

namespace Geeklog;

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;

class TwoFactorAuthentication
{
    // Number of digits of two factor auth code
    const NUM_DIGITS = 6;

    // Secret associated with a user
    const NUM_BITS_OF_SECRET = 160;

    // Image dimensions for QR code
    const QR_CODE_SIZE = 200;

    // Number of digits of each backup code
    const NUM_DIGITS_OF_BACKUP_CODE = 8;

    // Number of backup codes in database
    const NUM_BACKUP_CODES = 4;

    /**
     * Flag to show whether two factor auth is enabled for the current user
     *
     * @var bool
     */
    private $isEnabled = false;

    /**
     * User ID
     *
     * @var int
     */
    private $uid = 0;

    /**
     * @var TwoFactorAuth
     */
    private $tfa;

    /**
     * TwoFactorAuthentication constructor.
     */
    public function __construct()
    {
        global $_CONF, $_USER;

        $this->isEnabled = !COM_isAnonUser() &&
            isset($_CONF['enable_twofactorauth'], $_USER['twofactorauth_enabled']) &&
            $_CONF['enable_twofactorauth'] &&
            $_USER['twofactorauth_enabled'];

        if ($this->isEnabled) {
            $this->uid = (int) $_USER['uid'];

            if ($this->uid <= 1) {
                $this->isEnabled = false;
            }
        }
    }

    /**
     * Return if two factor auth is enabled for the current user
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Check if two factor auth is enabled for the current user
     *
     * @throws \LogicException
     */
    private function checkEnabled()
    {
        if (!$this->isEnabled) {
            throw new \LogicException('Two factor auth is disabled fo the current user.');
        }
    }

    /**
     * Return the only object of two factor auth class
     *
     * @return TwoFactorAuth
     */
    private function getTFAObject()
    {
        if (empty($this->tfa)) {
            $this->tfa = new TwoFactorAuth('Geeklog', self::NUM_DIGITS);
        }

        return $this->tfa;
    }

    /**
     * Return a secret code associated with the current user
     *
     * @return string
     */
    public function loadSecretFromDatabase()
    {
        global $_TABLES;
        static $secret = null;

        $this->checkEnabled();

        // Check for cached secret
        if ($secret === null) {
            $secret = DB_getItem($_TABLES['users'], 'twofactorauth_secret', "uid = {$this->uid}");
        }

        return $secret;
    }

    /**
     * Create and return a secret
     *
     * @return string
     * @throws TwoFactorAuthException
     */
    public function createSecret()
    {
        global $_TABLES;

        $this->checkEnabled();

        do {
            $secret = $this->tfa->createSecret(self::QR_CODE_SIZE);
            $done = (DB_count($_TABLES['users'], 'twofactorauth_secret', DB_escapeString($secret)) == 0);
        } while (!$done);

        return $secret;
    }

    /**
     * Save a secret to database
     *
     * @param  string $secret
     * @return bool   true on success, false otherwise
     */
    public function saveSecretToDatabase($secret)
    {
        global $_TABLES;

        $this->checkEnabled();

        $escapedSecret = DB_escapeString($secret);
        $sql = "UPDATE {$_TABLES['users']} SET twofactorauth_secret = '{$escapedSecret}' "
            . "WHERE (uid = {$this->uid})";
        DB_query($sql);

        return (DB_error() == '');
    }

    /**
     * Return QR code as a data URI
     *
     * @param  string $secret
     * @return string
     * @throws TwoFactorAuthException
     */
    public function getQRCodeImageAsDataURI($secret)
    {
        $this->checkEnabled();

        return $this->getTFAObject()
            ->getQRCodeImageAsDataUri('Geeklog', $secret, self::QR_CODE_SIZE);
    }

    /**
     * Return backup codes stored in database
     *
     * @return array of string
     */
    public function getBackupCodesFromDatabase()
    {
        global $_TABLES;

        $this->checkEnabled();

        $retval = array();
        $sql = "SELECT code FROM {$_TABLES['backup_codes']} "
            . "WHERE (uid = {$this->uid}) AND (is_used = 0) "
            . "ORDER BY code";
        $result = DB_query($sql);

        if (!DB_error()) {
            while (($A = DB_fetchArray($result, false)) !== false) {
                $retval[] = $A['code'];
            }
        }

        return $retval;
    }

    /**
     * Invalidate all the backup codes in database
     */
    private function invalidateBackupCodes()
    {
        global $_TABLES;

        $this->checkEnabled();
        $sql = "UPDATE {$_TABLES['backup_codes']} SET is_used = 1 "
            . "WHERE (uid = {$this->uid})";
        DB_query($sql);
    }

    /**
     * Create backup codes and save them into database
     *
     * @return array of string
     * @throws TwoFactorAuthException
     */
    public function createBackupCodes()
    {
        global $_TABLES;

        $this->checkEnabled();
        $this->invalidateBackupCodes();
        $retval = array();
        $tfa = $this->getTFAObject();

        for ($i = 0; $i < self::NUM_BACKUP_CODES; $i++) {
            do {
                $code = $tfa->createSecret(self::NUM_DIGITS_OF_BACKUP_CODE * 8);
                $done = (DB_count($_TABLES['backup_codes'], 'code', $code) == 0);
            } while (!$done);

            $escapedCode = DB_escapeString($code);
            $sql = "INSERT INTO {$_TABLES['backup_codes']} (code, uid, is_used) "
                . "VALUES ('{$escapedCode}', {$this->uid}, 0)";
            DB_query($sql);
            $retval[] = $code;
        }

        return $retval;
    }

    /**
     * Authenticate the user
     *
     * @param  string $code
     * @return bool
     */
    public function authenticate($code)
    {
        $this->checkEnabled();

        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== self::NUM_DIGITS) {
            return false;
        }

        $secret = $this->loadSecretFromDatabase();
        if (empty($secret)) {
            return false;
        }

        return $this->getTFAObject()->verifyCode($secret, $code);
    }
}