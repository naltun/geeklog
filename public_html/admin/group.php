<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Geeklog 2.2                                                               |
// +---------------------------------------------------------------------------+
// | group.php                                                                 |
// |                                                                           |
// | Geeklog group administration page.                                        |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2000-2017 by the following authors:                         |
// |                                                                           |
// | Authors: Tony Bibbs        - tony AT tonybibbs DOT com                    |
// |          Mark Limburg      - mlimburg AT users DOT sourceforge DOT net    |
// |          Jason Whittenburg - jwhitten AT securitygeeks DOT com            |
// |          Dirk Haun         - dirk AT haun-online DOT de                   |
// +---------------------------------------------------------------------------+
// |                                                                           |
// | This program is free software; you can redistribute it and/or             |
// | modify it under the terms of the GNU General Public License               |
// | as published by the Free Software Foundation; either version 2            |
// | of the License, or (at your option) any later version.                    |
// |                                                                           |
// | This program is distributed in the hope that it will be useful,           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
// | GNU General Public License for more details.                              |
// |                                                                           |
// | You should have received a copy of the GNU General Public License         |
// | along with this program; if not, write to the Free Software Foundation,   |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.           |
// |                                                                           |
// +---------------------------------------------------------------------------+

/**
 * This file is the Geeklog Group administration page
 *
 * @author   Tony Bibbs, tony AT tonybibbs DOT com
 */

/**
 * Geeklog common function library
 */
require_once '../lib-common.php';

/**
 * Security check to ensure user even belongs on this page
 */
require_once 'auth.inc.php';

// Uncomment the line below if you need to debug the HTTP variables being passed
// to the script.  This will sometimes cause errors but it will allow you to see
// the data being passed in a POST operation
// echo COM_debug($_POST);

// Set this to true to get various debug messages from this script
$_GROUP_VERBOSE = false;

$display = '';

// Make sure user has rights to access this page
if (!SEC_hasRights('group.edit')) {
    $display .= COM_showMessageText($MESSAGE[29], $MESSAGE[30]);
    $display = COM_createHTMLDocument($display, array('pagetitle' => $MESSAGE[30]));
    COM_accessLog("User {$_USER['username']} tried to illegally access the group administration screen.");
    COM_output($display);
    exit;
}

/**
 * Shows the group editor form
 *
 * @param    string $grp_id ID of group to edit
 * @return   string      HTML for group editor
 */
function editgroup($grp_id = '')
{
    global $_TABLES, $_CONF, $_USER, $LANG_ACCESS, $LANG_ADMIN, $MESSAGE,
           $LANG28, $_GROUP_VERBOSE, $_GROUP_MAINGROUPS, $_GROUP_LOOPGROUPS;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';

    $thisUsersGroups = SEC_getUserGroups();
    if (!empty($grp_id) && ($grp_id > 0) &&
        !in_array($grp_id, $thisUsersGroups) &&
        !SEC_groupIsRemoteUserAndHaveAccess($grp_id, $thisUsersGroups)
    ) {
        if (!SEC_inGroup('Root') && (DB_getItem($_TABLES['groups'],
                    'grp_name', "grp_id = $grp_id") == 'Root')
        ) {
            $retval .= COM_showMessageText($LANG_ACCESS['canteditroot'],
                $LANG_ACCESS['groupeditor']);
            COM_accessLog("User {$_USER['username']} tried to edit the Root group with insufficient privileges.");
        } else {
            $retval .= COM_showMessageText($LANG_ACCESS['canteditgroup'],
                $LANG_ACCESS['groupeditor']);
        }

        return $retval;
    }

    $group_templates = COM_newTemplate(CTL_core_templatePath($_CONF['path_layout'] . 'admin/group'));
    $group_templates->set_file('editor', 'groupeditor.thtml');
    $group_templates->set_block('editor', 'no-group-right-message');
    $group_templates->set_block('editor', 'rights_list_options');
    $group_templates->set_block('editor', 'rights_list_option');
    $group_templates->set_block('editor', 'rights_list_option_disabled');

    $group_templates->set_var('lang_save', $LANG_ADMIN['save']);
    $group_templates->set_var('lang_cancel', $LANG_ADMIN['cancel']);
    $group_templates->set_var('lang_admingroup', $LANG28[49]);
    $group_templates->set_var('lang_admingrp_msg', $LANG28[50]);
    $group_templates->set_var('lang_defaultgroup', $LANG28[88]);
    $group_templates->set_var('lang_defaultgrp_msg', $LANG28[89]);
    $group_templates->set_var('lang_applydefault_msg', $LANG28[90]);
    $group_templates->set_var('lang_groupname', $LANG_ACCESS['groupname']);
    $group_templates->set_var('lang_description', $LANG_ACCESS['description']);
    $group_templates->set_var('lang_securitygroups',
        $LANG_ACCESS['securitygroups']);
    $group_templates->set_var('lang_rights', $LANG_ACCESS['rights']);

    $showall = 0;
    if (Geeklog\Input::request('chk_showall') == 1) {
        $showall = 1;
    }
    $group_templates->set_var('show_all', $showall);

    if (!empty($grp_id)) {
        $result = DB_query("SELECT grp_id,grp_name,grp_descr,grp_gl_core,grp_default FROM {$_TABLES['groups']} WHERE grp_id ='$grp_id'");
        $A = DB_fetchArray($result);
        if ($A['grp_gl_core'] > 0) {
            $group_templates->set_var('chk_adminuse', 'checked="checked"');
        }
        if ($A['grp_default'] != 0) {
            $group_templates->set_var('chk_defaultuse', 'checked="checked"');
        }
    } else {
        // new group, so it's obviously not a core group
        $A['grp_gl_core'] = 0;
        $A['grp_default'] = 0;
    }

    $token = SEC_createToken();
    $retval .= COM_startBlock($LANG_ACCESS['groupeditor'], '',
        COM_getBlockTemplate('_admin_block', 'header'));
    $retval .= SEC_getTokenExpiryNotice($token);

    if (!empty($grp_id)) {
        // Groups tied to Geeklog's functionality shouldn't be deleted
        if ($A['grp_gl_core'] != 1) {
            $group_templates->set_var('allow_delete', true);
            $group_templates->set_var('lang_delete', $LANG_ADMIN['delete']);
            $group_templates->set_var('confirm_message', $MESSAGE[76]);

            $group_templates->set_var('group_core', 0);
        } else {
            $group_templates->set_var('group_core', 1);
        }
        $group_templates->set_var('group_id', $A['grp_id']);
    } else {
        $group_templates->set_var('group_core', 0);
    }

    if ($A['grp_gl_core'] != 1) {
        $group_templates->set_var('groupname_inputtype', 'text');
        $group_templates->set_var('groupname_static', '');
    } else {
        $group_templates->set_var('groupname_inputtype', 'hidden');
        $group_templates->set_var('groupname_static', $A['grp_name']);
    }
    if (isset($A['grp_name'])) {
        $group_templates->set_var('group_name', $A['grp_name']);

        switch ($A['grp_name']) {
            case 'All Users':
            case 'Logged-in Users':
            case 'Remote Users':
                $group_templates->set_var('hide_defaultoption',
                    ' style="display:none;"');
                break;

            default:
                $group_templates->set_var('hide_defaultoption', '');
                break;
        }

    } else {
        $group_templates->set_var('group_name', '');
    }

    if (isset($A['grp_descr'])) {
        $group_templates->set_var('group_description', $A['grp_descr']);
    } else {
        $group_templates->set_var('group_description', '');
    }

    $selected = '';
    if (!empty($grp_id)) {
        $tmp = DB_query("SELECT ug_main_grp_id FROM {$_TABLES['group_assignments']} WHERE ug_grp_id = $grp_id");
        $num_groups = DB_numRows($tmp);
        for ($x = 0; $x < $num_groups; $x++) {
            $G = DB_fetchArray($tmp);
            if ($x > 0) {
                $selected .= ' ' . $G['ug_main_grp_id'];
            } else {
                $selected .= $G['ug_main_grp_id'];
            }
        }
    }

    $groupoptions = '';
    if ($A['grp_gl_core'] == 1) {
        $group_templates->set_var('lang_securitygroupmsg',
            $LANG_ACCESS['coregroupmsg']);
        $group_templates->set_var('hide_adminoption',
            ' style="display:none;"');

        $count = 0;
        if (!empty($selected)) {
            $inclause = str_replace(' ', ',', $selected);
            $result = DB_query("SELECT COUNT(*) AS count FROM {$_TABLES['groups']} WHERE grp_id <> $grp_id AND grp_id IN ($inclause)");
            list($count) = DB_fetchArray($result);
        }
        if ($count == 0) {
            // this group doesn't belong to anything...give a friendly message
            $group_templates->set_var('lang_message', $LANG_ACCESS['nogroupsforcoregroup']);
            $groupoptions = $group_templates->parse('editor-message', 'no-group-right-message');        
        }
    } else {
        $group_templates->set_var('lang_securitygroupmsg',
            $LANG_ACCESS['groupmsg']);
        $group_templates->set_var('hide_adminoption', '');
    }
    if ($_GROUP_VERBOSE) {
        COM_errorLog("SELECTED: $selected");
    }

    if (empty($groupoptions)) {
        // make sure to list only those groups of which the Group Admin
        // is a member
        $whereGroups = '(grp_id IN (' . implode(',', $thisUsersGroups) . '))';

        $header_arr = array(
            array('text' => $LANG28[86], 'field' => ($A['grp_gl_core'] == 1 ? 'disabled-checkbox' : 'checkbox'), 'sort' => false),
            array('text' => $LANG_ACCESS['groupname'], 'field' => 'grp_name', 'sort' => true),
            array('text' => $LANG_ACCESS['description'], 'field' => 'grp_descr', 'sort' => true),
        );

        $defsort_arr = array('field' => 'grp_name', 'direction' => 'asc');

        $form_url = $_CONF['site_admin_url']
            . '/group.php?mode=edit&amp;grp_id=' . $grp_id;
        $text_arr = array('has_menu' => false,
                          'title'    => '', 'instructions' => '',
                          'icon'     => '', 'form_url' => $form_url,
                          'inline'   => true);

        if ($A['grp_gl_core'] == 1) {
            $inclause = str_replace(' ', ',', $selected);
            $sql = "SELECT grp_id, grp_name, grp_descr, grp_gl_core FROM {$_TABLES['groups']} WHERE grp_id <> $grp_id AND grp_id IN ($inclause)";
        } else {
            $xsql = '';
            if (!empty($grp_id)) {
                $xsql = " AND (grp_id <> $grp_id)";
            }
            $sql = "SELECT grp_id, grp_name, grp_descr, grp_gl_core FROM {$_TABLES['groups']} WHERE (grp_name <> 'Root')" . $xsql . ' AND ' . $whereGroups;
        }

        // Create a complete list of inherited groups for this group being edited so we know what needs to be disabled on screen
        $resultY = DB_query($sql, 1);
        $nrowsY = DB_numRows($resultY);
        $_GROUP_MAINGROUPS = array(); // Inherited groups from the actual groups the current group belongs to
        $_GROUP_LOOPGROUPS = array(); // Groups that current group cannot belong to as it would create a loop on to itself (ie A can belong to B, B can belong to C but C cannot belong to A as it would then create a loop)
        for ($iY = 1; $iY <= $nrowsY; $iY++) {
            $groups = array();
            $Y = DB_fetchArray($resultY);
            //Figure out if group being listed is already an inherited group
            
            $resultZ = DB_query("SELECT ug_main_grp_id,grp_name FROM {$_TABLES["group_assignments"]},{$_TABLES["groups"]}"
                    . " WHERE grp_id = ug_main_grp_id AND ug_grp_id = " . $Y['grp_id'], 1);
            $nrowsZ = DB_numRows($resultZ);
            while ($nrowsZ > 0) {
                $inheritedgroups = array();

                for ($i = 1; $i <= $nrowsZ; $i++) {
                    $Z = DB_fetchArray($resultZ);

                    if (!in_array($Z['ug_main_grp_id'], $groups)) {
                        array_push($inheritedgroups, $Z['ug_main_grp_id']);
                        $groups[$Z['grp_name']] = $Z['ug_main_grp_id'];
                    }
                }

                if (count($inheritedgroups) > 0) {
                    $glist = implode(',', $inheritedgroups);
                    $resultZ = DB_query("SELECT ug_main_grp_id,grp_name FROM {$_TABLES["group_assignments"]},{$_TABLES["groups"]}"
                            . " WHERE grp_id = ug_main_grp_id AND ug_grp_id IN ($glist)", 1);
                    $nrowsZ = DB_numRows($resultZ);
                } else {
                    $nrowsZ = 0;
                }
            }
            /*
            // Check if part of inherited and if selected part of inherited
            if (in_array($grp_id, $groups) OR in_array($Y['grp_id'], explode(' ', $selected))) {
                $_GROUP_MAINGROUPS = array_merge($_GROUP_MAINGROUPS, $groups);
                // Add top group
                $_GROUP_MAINGROUPS[$Y['grp_name']] = $Y['grp_id'];                        
            } 
            */            
            // Check if could create a security group loop and then check, if part of inherited and if selected part of inherited
            if (in_array($grp_id, $groups)) { 
                $_GROUP_LOOPGROUPS = array_merge($_GROUP_LOOPGROUPS, $groups);
                // Add loop group
                $_GROUP_LOOPGROUPS[$Y['grp_name']] = $Y['grp_id'];
            } elseif (in_array($Y['grp_id'], explode(' ', $selected))) {
                $_GROUP_MAINGROUPS = array_merge($_GROUP_MAINGROUPS, $groups);
                // Add top group
                $_GROUP_MAINGROUPS[$Y['grp_name']] = $Y['grp_id'];
            } 
        }

        $query_arr = array('table'          => 'groups',
                           'sql'            => $sql,
                           'query_fields'   => array('grp_name'),
                           'default_filter' => '',
                           'query'          => '',
                           'query_limit'    => 0);

        $groupoptions = ADMIN_list('groups', 'ADMIN_getListField_groups',
            $header_arr, $text_arr, $query_arr,
            $defsort_arr, '', explode(' ', $selected));
    }
    $group_templates->set_var('group_options', $groupoptions);

    if ($A['grp_gl_core'] == 1) {
        $group_templates->set_var('lang_rightsmsg', $LANG_ACCESS['corerightsdescr']);
    } else {
        $group_templates->set_var('lang_rightsmsg', $LANG_ACCESS['rightsdescr']);
    }

    $group_templates->set_var('rights_options',
        printrights($grp_id, $A['grp_gl_core'], $group_templates));
    $group_templates->set_var('gltoken_name', CSRF_TOKEN);
    $group_templates->set_var('gltoken', $token);
    $group_templates->parse('output', 'editor');
    $retval .= $group_templates->finish($group_templates->get_var('output'));
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}


/**
 * Get the indirect features for a group, i.e. a list of all the features
 * that this group inherited from other groups.
 *
 * @param    int $grp_id ID of group
 * @return   string   comma-separated list of feature names
 */
function getIndirectFeatures($grp_id)
{
    global $_TABLES;

    $checked = array();
    $tocheck = array($grp_id);

    do {
        $grp = array_pop($tocheck);

        $result = DB_query("SELECT ug_main_grp_id FROM {$_TABLES['group_assignments']} WHERE ug_grp_id = $grp AND ug_uid IS NULL");
        $numrows = DB_numRows($result);

        $checked[] = $grp;

        for ($j = 0; $j < $numrows; $j++) {
            $A = DB_fetchArray($result);
            if (!in_array($A['ug_main_grp_id'], $checked) &&
                !in_array($A['ug_main_grp_id'], $tocheck)
            ) {
                $tocheck[] = $A['ug_main_grp_id'];
            }
        }
    } while (count($tocheck) > 0);

    // get features for all groups in $checked
    $glist = implode(',', $checked);
    $result = DB_query("SELECT DISTINCT ft_name FROM {$_TABLES['access']},{$_TABLES['features']} WHERE ft_id = acc_ft_id AND acc_grp_id IN ($glist)");
    $nrows = DB_numRows($result);

    $retval = '';
    for ($j = 1; $j <= $nrows; $j++) {
        $A = DB_fetchArray($result);
        $retval .= $A['ft_name'];
        if ($j < $nrows) {
            $retval .= ',';
        }
    }

    return $retval;
}

/**
 * Remove indirect features
 *
 * @param  int   $grp_id   ID of the group
 * @param  array $features array of feature IDs
 * @return array
 */
function removeIndirectFeatures($grp_id, array $features)
{
    global $_TABLES;

    $retval = array();

    if (count($features) > 0) {
        $indirectFeatureNames = trim(getIndirectFeatures($grp_id));

        if ($indirectFeatureNames !== '') {
            $indirectFeatureNames = explode(',', $indirectFeatureNames);
            $indirectFeatureNames = array_map('DB_escapeString', $indirectFeatureNames);
            $sql = "SELECT ft_id FROM {$_TABLES['features']} "
                . "WHERE ft_name IN ('" . implode("', '", $indirectFeatureNames) . "')";
            $result = DB_query($sql);
            $indirectFeatures = array();

            while (($A = DB_fetchArray($result, false)) !== false) {
                $indirectFeatures[] = $A['ft_id'];
            }

            foreach ($features as $feature) {
                if (!in_array($feature, $indirectFeatures)) {
                    $retval[] = $feature;
                }
            }
        } else {
            // There is no indirect feature
            $retval = $features;
        }
    }

    return $retval;
}

/**
 * Prints the features a group has access.  Please follow the comments in the
 * code closely if you need to modify this function. Also right is synonymous
 * with feature.
 *
 * @param    mixed     $grp_id           ID to print rights for
 * @param    boolean   $core             indicates if group is a core Geeklog group
 * @param    Template  $group_templates  Template instance
 * @return   string    HTML for rights
 */
function printrights($grp_id = '', $core = 0, Template $group_templates = null)
{
    global $_TABLES, $_USER, $LANG_ACCESS, $_GROUP_VERBOSE;

    $num_cols = 2;  // number of columns
    // this gets a bit complicated so bear with the comments

    // get a list of all the features that the current user (i.e. Group Admin)
    // has access to, so we only include these features in the list below
    if (!SEC_inGroup('Root')) {
        $GroupAdminFeatures = SEC_getUserPermissions();
        $availableFeatures = explode(',', $GroupAdminFeatures);
        $GroupAdminFeatures = "'" . implode("','", $availableFeatures) . "'";
        $ftWhere = ' WHERE ft_name IN (' . $GroupAdminFeatures . ')';
    } else {
        $ftWhere = '';
    }

    // now query for all available features
    $features = DB_query("SELECT ft_id,ft_name,ft_descr FROM {$_TABLES['features']}{$ftWhere} ORDER BY ft_name");
    $nfeatures = DB_numRows($features);

    $grpftarray = array();
    if (!empty($grp_id)) {
        // now get all the feature this group gets directly
        $directfeatures = DB_query("SELECT acc_ft_id,ft_name FROM {$_TABLES['access']},{$_TABLES['features']} WHERE ft_id = acc_ft_id AND acc_grp_id = $grp_id", 1);

        // now in many cases the features will be given to this user indirectly
        // via membership to another group.  These are not editable and must,
        // instead, be removed from that group directly
        $indirectfeatures = getIndirectFeatures($grp_id);
        $indirectfeatures = explode(',', $indirectfeatures);

        // Build an array of indirect features
        for ($i = 0; $i < count($indirectfeatures); $i++) {
            $grpftarray[current($indirectfeatures)] = 'indirect';
            next($indirectfeatures);
        }

        // Build an arrray of direct features
        $grpftarray1 = array();
        $ndirect = DB_numRows($directfeatures);
        for ($i = 0; $i < $ndirect; $i++) {
            $A = DB_fetchArray($directfeatures);
            $grpftarray1[$A['ft_name']] = 'direct';
        }

        // Now merge the two arrays
        $grpftarray = array_merge($grpftarray, $grpftarray1);
        if ($_GROUP_VERBOSE) {
            // this is for debugging purposes
            for ($i = 1; $i < count($grpftarray); $i++) {
                COM_errorLog("element $i is feature " . key($grpftarray) . " and is " . current($grpftarray), 1);
                next($grpftarray);
            }
        }
    }

    // OK, now loop through and print all the features giving edit rights
    // to only the ones that are direct features
    $ftcount = 0;
    for ($i = 0; $i < $nfeatures; $i++) {
        $id = 'id-features' . $i;
        $A = DB_fetchArray($features);

        if ((empty($grpftarray[$A['ft_name']]) OR ($grpftarray[$A['ft_name']] == 'direct')) AND ($core != 1)) {
            $ftcount++;
            $group_templates->set_var('id', $id);
            $group_templates->set_var('value', $A['ft_id']);
            $group_templates->set_var('checked', ''); // reset this as it goes through a loop
            if (!empty($grpftarray[$A['ft_name']])) {
                if ($grpftarray[$A['ft_name']] == 'direct') {
                    $group_templates->set_var('checked', '1');
                }
            }
            $group_templates->set_var('title', $A['ft_descr']);
            $group_templates->set_var('feature_name', $A['ft_name']);
            $group_templates->parse('rights_list_options', 'rights_list_option', true);
        } else {
            // either this is an indirect right OR this is a core feature
            if ((($core == 1) AND (isset($grpftarray[$A['ft_name']]) AND (($grpftarray[$A['ft_name']] == 'indirect') OR ($grpftarray[$A['ft_name']] == 'direct')))) OR ($core != 1)) {
                $ftcount++;    
                $group_templates->set_var('value', $A['ft_id']);
                $group_templates->set_var('title', $A['ft_descr']);
                $group_templates->set_var('feature_name', $A['ft_name']);
                $group_templates->parse('rights_list_options', 'rights_list_option_disabled', true);
            }
        }
    }    
    
    if ($ftcount == 0) {
        // This group doesn't have rights to any features
        $group_templates->set_var('lang_message', $LANG_ACCESS['grouphasnorights']);
        $retval = $group_templates->parse('editor-message', 'no-group-right-message');        
        
    } else {
        $retval = $group_templates->parse('editor-rights', 'rights_list_options');        
    }

    return $retval;
}

/**
 * Add or remove a default group to/from all existing accounts
 *
 * @param    int     $grp_id ID of default group
 * @param    boolean $add    true: add, false: remove
 * @return   void
 */
function applydefaultgroup($grp_id, $add = true)
{
    global $_TABLES, $_GROUP_VERBOSE;

    /**
     * In the "add" case, we have to insert one record for each user. Pack this
     * many values into one INSERT statement to save some time and bandwidth.
     */
    $_values_per_insert = 25;

    if ($_GROUP_VERBOSE) {
        if ($add) {
            COM_errorLog("Adding group '$grp_id' to all user accounts");
        } else {
            COM_errorLog("Removing group '$grp_id' from all user accounts");
        }
    }

    if ($add) {
        $result = DB_query("SELECT uid FROM {$_TABLES['users']} WHERE uid > 1");
        $num_users = DB_numRows($result);
        for ($i = 0; $i < $num_users; $i += $_values_per_insert) {
            $u = array();
            for ($j = 0; $j < $_values_per_insert; $j++) {
                list($uid) = DB_fetchArray($result);
                $u[] = $uid;
                if ($i + $j + 1 >= $num_users) {
                    break;
                }
            }
            $v = "($grp_id," . implode("), ($grp_id,", $u) . ')';
            DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_uid) VALUES " . $v);
        }
    } else {
        DB_query("DELETE FROM {$_TABLES['group_assignments']} WHERE (ug_main_grp_id = $grp_id) AND (ug_grp_id IS NULL)");
    }
}

/**
 * Save a group to the database
 *
 * @param    string  $grp_id           ID of group to save
 * @param    string  $grp_name         Group Name
 * @param    string  $grp_descr        Description of group
 * @param    boolean $grp_admin        Flag that indicates this is an admin use group
 * @param    boolean $grp_gl_core      Flag that indicates if this is a core Geeklog group
 * @param    boolean $grp_default      Flag that indicates if this is a default group
 * @param    boolean $grp_applydefault Flag that indicates whether to apply a change in $grp_default to all existing
 *                                     user accounts
 * @param    array   $features         Features the group has access to
 * @param    array   $groups           Groups this group will belong to
 * @return   string                    HTML refresh or error message
 */
function savegroup($grp_id, $grp_name, $grp_descr, $grp_admin, $grp_gl_core, $grp_default, $grp_applydefault, $features, $groups)
{
    global $_CONF, $_TABLES, $_USER, $LANG_ACCESS, $_GROUP_VERBOSE;

    $retval = '';
    if (!empty($grp_name) && !empty($grp_descr)) {
        $GroupAdminGroups = SEC_getUserGroups();
        if (!empty($grp_id) &&
            ($grp_id > 0) &&
            !in_array($grp_id, $GroupAdminGroups) &&
            !SEC_groupIsRemoteUserAndHaveAccess($grp_id, $GroupAdminGroups)
        ) {
            COM_accessLog("User {$_USER['username']} tried to edit group '$grp_name' ($grp_id) with insufficient privileges.");
            COM_redirect($_CONF['site_admin_url'] . '/group.php');
        }

        if ($grp_gl_core == 1 && !is_array($features)) {
            COM_errorLog("Sorry, no valid features were passed to this core group ($grp_id) and saving could cause problem...bailing.");
            COM_redirect($_CONF['site_admin_url'] . '/group.php');
        }

        // group names have to be unique, so check if this one exists already
        $g_id = DB_getItem($_TABLES['groups'], 'grp_id', "grp_name = '$grp_name'");
        if ($g_id > 0) {
            if (empty($grp_id) || ($grp_id != $g_id)) {
                // there already is a group with that name - complain
                $retval .= COM_showMessageText($LANG_ACCESS['groupexistsmsg'],
                        $LANG_ACCESS['groupexists'])
                    . editgroup($grp_id);
                $retval = COM_createHTMLDocument($retval, array('pagetitle' => $LANG_ACCESS['groupeditor']));

                return $retval;
            }
        }

        $grp_descr = DB_escapeString($grp_descr);

        $grp_applydefault_add = true;
        if (empty($grp_id)) {
            DB_save($_TABLES['groups'],
                'grp_name,grp_descr,grp_gl_core,grp_default',
                "'$grp_name','$grp_descr',$grp_gl_core,$grp_default");
            $grp_id = DB_getItem($_TABLES['groups'], 'grp_id',
                "grp_name = '$grp_name'");
            $new_group = true;
        } else {
            if ($grp_applydefault == 1) {
                // check if $grp_default changed
                $old_default = DB_getItem($_TABLES['groups'], 'grp_default',
                    "grp_id = $grp_id");
                if ($old_default == $grp_default) {
                    // no change required
                    $grp_applydefault = 0;
                } elseif ($old_default == 1) {
                    $grp_applydefault_add = false;
                }
            }

            DB_save($_TABLES['groups'],
                'grp_id,grp_name,grp_descr,grp_gl_core,grp_default',
                "$grp_id,'$grp_name','$grp_descr',$grp_gl_core,$grp_default");
            $new_group = false;
        }

        if (empty($grp_id) || ($grp_id < 1)) {
            // "this shouldn't happen"
            COM_errorLog("Internal error: invalid group id");
            $retval .= COM_showMessage(95);
            $retval = COM_createHTMLDocument($retval, array('pagetitle' => $LANG_ACCESS['groupeditor']));

            return $retval;
        }

        // Use the field grp_gl_core to indicate if this non-core GL Group
        // is an Admin related group
        if (($grp_gl_core != 1) AND ($grp_id > 1)) {
            if ($grp_admin == 1) {
                DB_query("UPDATE {$_TABLES['groups']} SET grp_gl_core=2 WHERE grp_id=$grp_id");
            } else {
                DB_query("UPDATE {$_TABLES['groups']} SET grp_gl_core=0 WHERE grp_id=$grp_id");
            }
        }

        // now save the features
        DB_delete($_TABLES['access'], 'acc_grp_id', $grp_id);

        // Remove features inherited from groups (bug #642)
        $features = removeIndirectFeatures($grp_id, $features);

        if (count($features) > 0) {
            if (SEC_inGroup('Root')) {
                foreach ($features as $f) {
                    DB_query("INSERT INTO {$_TABLES['access']} (acc_ft_id,acc_grp_id) VALUES ($f,$grp_id)");
                }
            } else {
                $GroupAdminFeatures = SEC_getUserPermissions();
                $availableFeatures = explode(',', $GroupAdminFeatures);
                foreach ($features as $f) {
                    if (in_array($f, $availableFeatures)) {
                        DB_query("INSERT INTO {$_TABLES['access']} (acc_ft_id,acc_grp_id) VALUES ($f,$grp_id)");
                    }
                }
            }
        }

        if ($_GROUP_VERBOSE) {
            COM_errorLog('groups = ' . $groups);
            COM_errorLog("deleting all group_assignments for group $grp_id/$grp_name", 1);
        }

        DB_delete($_TABLES['group_assignments'], 'ug_grp_id', $grp_id);
        if (!empty($groups)) {
            foreach ($groups as $g) {
                if (in_array($g, $GroupAdminGroups)) {
                    if ($_GROUP_VERBOSE) {
                        COM_errorLog("adding group_assignment $g for $grp_name", 1);
                    }
                    $sql = "INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_grp_id) VALUES ($g,$grp_id)";
                    DB_query($sql);
                }
            }
        }

        // Make sure Root group belongs to any new group
        if (DB_getItem($_TABLES['group_assignments'], 'COUNT(*)',
                "ug_main_grp_id = $grp_id AND ug_grp_id = 1") == 0
        ) {
            DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_grp_id) VALUES ($grp_id, 1)");
        }

        // make sure this Group Admin belongs to the new group
        if (!SEC_inGroup('Root')) {
            if (DB_count($_TABLES['group_assignments'], 'ug_uid',
                    "(ug_uid = {$_USER['uid']}) AND (ug_main_grp_id = $grp_id)") == 0
            ) {
                DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_uid) VALUES ($grp_id,{$_USER['uid']})");
            }
        }

        if ($grp_applydefault == 1) {
            applydefaultgroup($grp_id, $grp_applydefault_add);
        }

        if ($new_group) {
            PLG_groupChanged($grp_id, 'new');
        } else {
            PLG_groupChanged($grp_id, 'edit');
        }
        if (Geeklog\Input::request('chk_showall') == 1) {
            COM_redirect($_CONF['site_admin_url'] . '/group.php?msg=49&chk_showall=1');
        } else {
            COM_redirect($_CONF['site_admin_url'] . '/group.php?msg=49');
        }
    } else {
        $retval .= COM_showMessageText($LANG_ACCESS['missingfieldsmsg'],
                $LANG_ACCESS['missingfields'])
            . editgroup($grp_id);
        $retval = COM_createHTMLDocument($retval, array('pagetitle' => $LANG_ACCESS['groupeditor']));

        return $retval;
    }
}

/**
 * Get a list (actually an array) of all groups this group belongs to.
 *
 * @param    int $basegroup id of group
 * @return   array               array of all groups $basegroup belongs to
 */
function getGroupList($basegroup)
{
    global $_TABLES;

    $to_check = array();
    array_push($to_check, $basegroup);

    $checked = array();

    while (count($to_check) > 0) {
        $thisgroup = array_pop($to_check);
        if ($thisgroup > 0) {
            $result = DB_query("SELECT ug_grp_id FROM {$_TABLES['group_assignments']} WHERE ug_main_grp_id = $thisgroup");
            $numGroups = DB_numRows($result);
            for ($i = 0; $i < $numGroups; $i++) {
                $A = DB_fetchArray($result);
                if (!in_array($A['ug_grp_id'], $checked)) {
                    if (!in_array($A['ug_grp_id'], $to_check)) {
                        array_push($to_check, $A['ug_grp_id']);
                    }
                }
            }
            $checked[] = $thisgroup;
        }
    }

    return $checked;
}

/**
 * Display a list of all users in a given group.
 *
 * @param   int $grp_id group id
 * @return  string               HTML for user listing
 */
function listusers($grp_id)
{
    global $_CONF, $_TABLES, $LANG28, $LANG_ACCESS, $LANG_ADMIN, $_IMAGE_TYPE;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';

    $thisUsersGroups = SEC_getUserGroups();
    if (!empty($grp_id) && ($grp_id > 0) &&
        !in_array($grp_id, $thisUsersGroups) &&
        !SEC_groupIsRemoteUserAndHaveAccess($grp_id, $thisUsersGroups)
    ) {
        $retval .= COM_showMessageText($LANG_ACCESS['cantlistgroup'],
            $LANG_ACCESS['usergroupadmin']);

        return $retval;
    }

    if ($_CONF['lastlogin']) {
        $login_text = $LANG28[41];
        $login_field = 'lastlogin';
    } else {
        $login_text = $LANG28[40];
        $login_field = 'regdate';
    }

    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        array('text' => $LANG28[37], 'field' => 'uid', 'sort' => true),
        array('text' => $LANG28[3], 'field' => 'username', 'sort' => true),
        array('text' => $LANG28[4], 'field' => 'fullname', 'sort' => true),
        array('text' => $login_text, 'field' => $login_field, 'sort' => true),
        array('text' => $LANG28[7], 'field' => 'email', 'sort' => true),
    );

    $defsort_arr = array('field'     => 'username',
                         'direction' => 'asc',
    );

    $form_url = $_CONF['site_admin_url'] . '/group.php?mode=listusers&amp;grp_id=' . $grp_id;
    if (Geeklog\Input::request('chk_showall') == 1) {
        $form_url .= '&amp;chk_showall=1';
    }

    $groupname = DB_getItem($_TABLES['groups'], 'grp_name', "grp_id = '$grp_id'");
    $headline = sprintf($LANG_ACCESS['usersingroup'], $groupname);

    $url = $_CONF['site_admin_url'] . '/group.php';
    if (Geeklog\Input::request('chk_showall') == 1) {
        $url .= '?chk_showall=1';
    }
    $menu_arr = array(
        array(
            'url'  => $url,
            'text' => $LANG28[38],
        ),
        array(
            'url'  => $_CONF['site_admin_url'],
            'text' => $LANG_ADMIN['admin_home'],
        ),
    );

    $retval .= COM_startBlock($headline, '', COM_getBlockTemplate('_admin_block', 'header'));
    $retval .= ADMIN_createMenu(
        $menu_arr,
        $LANG_ACCESS['usersingroupmsg'],
        $_CONF['layout_url'] . '/images/icons/group.' . $_IMAGE_TYPE
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url'   => $form_url,
        'help_url'   => '',
    );

    $join_userinfo = '';
    $select_userinfo = '';
    if ($_CONF['lastlogin']) {
        $join_userinfo = "LEFT JOIN {$_TABLES['user_attributes']} ON {$_TABLES['users']}.uid={$_TABLES['user_attributes']}.uid ";
        $select_userinfo = ",lastlogin ";
    }

    $groups = getGroupList($grp_id);
    $groupList = implode(',', $groups);

    $sql = "SELECT DISTINCT {$_TABLES['users']}.uid,username,fullname,email,photo,regdate$select_userinfo "
        . "FROM {$_TABLES['group_assignments']},{$_TABLES['users']} $join_userinfo "
        . "WHERE {$_TABLES['users']}.uid > 1 "
        . "AND {$_TABLES['users']}.uid = {$_TABLES['group_assignments']}.ug_uid "
        . "AND ({$_TABLES['group_assignments']}.ug_main_grp_id IN ({$groupList}))";

    $query_arr = array('table'          => 'users',
                       'sql'            => $sql,
                       'query_fields'   => array('username', 'email', 'fullname'),
                       'default_filter' => "AND {$_TABLES['users']}.uid > 1",
    );

    $retval .= ADMIN_list('user', 'ADMIN_getListField_users', $header_arr,
        $text_arr, $query_arr, $defsort_arr);
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}

/**
 * Display a list of (all) groups
 *
 * @param    boolean $show_all_groups include admin groups if true
 * @return   string                          HTML of the group list
 */
function listgroups($show_all_groups = false)
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS, $LANG28, $_IMAGE_TYPE;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';

    $header_arr = array(      // display 'text' and use table field 'field'
        array('text' => $LANG_ADMIN['edit'], 'field' => 'edit', 'sort' => false),
        array('text' => $LANG_ACCESS['groupname'], 'field' => 'grp_name', 'sort' => true),
        array('text' => $LANG_ACCESS['description'], 'field' => 'grp_descr', 'sort' => true),
        array('text' => $LANG_ACCESS['coregroup'], 'field' => 'grp_gl_core', 'sort' => true),
        array('text' => $LANG28[88], 'field' => 'grp_default', 'sort' => true),
        array('text' => $LANG_ACCESS['listusers'], 'field' => 'list', 'sort' => false),
    );

    $defsort_arr = array('field' => 'grp_name', 'direction' => 'asc');

    $form_url = $_CONF['site_admin_url'] . '/group.php';
    $edit_url = $_CONF['site_admin_url'] . '/group.php?mode=edit';
    if ($show_all_groups) {
        $form_url .= '?chk_showall=1';
        $edit_url .= '&amp;chk_showall=1';
    }

    $menu_arr = array(
        array('url'  => $edit_url,
              'text' => $LANG_ADMIN['create_new']),
        array('url'  => $_CONF['site_admin_url'],
              'text' => $LANG_ADMIN['admin_home']),
    );

    $retval .= COM_startBlock($LANG_ACCESS['groupmanager'], '',
        COM_getBlockTemplate('_admin_block', 'header'));

    $retval .= ADMIN_createMenu(
        $menu_arr,
        $LANG_ACCESS['newgroupmsg'],
        $_CONF['layout_url'] . '/images/icons/group.' . $_IMAGE_TYPE
    );

    $text_arr = array(
        'has_extras' => true,
        'form_url'   => $form_url,
    );

    $checked = '';
    if ($show_all_groups) {
        $checked = ' checked="checked"';
    }

    if (SEC_inGroup('Root')) {
        $grpFilter = '';
    } else {
        $thisUsersGroups = SEC_getUserGroups();
        $grpFilter = 'AND (grp_id IN (' . implode(',', $thisUsersGroups) . '))';
    }

    if ($show_all_groups) {
        $checked = true;
        $query_arr = array(
            'table'          => 'groups',
            'sql'            => "SELECT * FROM {$_TABLES['groups']} WHERE 1=1",
            'query_fields'   => array('grp_name', 'grp_descr'),
            'default_filter' => $grpFilter);
    } else {
        $query_arr = array(
            'table'          => 'groups',
            'sql'            => "SELECT * FROM {$_TABLES['groups']} WHERE (grp_gl_core = 0 OR grp_name IN ('All Users','Logged-in Users'))",
            'query_fields'   => array('grp_name', 'grp_descr'),
            'default_filter' => $grpFilter);
    }

    $filter = COM_createControl('type-checkbox', array(
        'name'       => 'chk_showall',
        'id'         => 'chk_showall',
        'value'      => '1',
        'checked'    => $checked,
        'lang_label' => $LANG28[48]
    ));

    $retval .= ADMIN_list('groups', 'ADMIN_getListField_groups', $header_arr,
        $text_arr, $query_arr, $defsort_arr, $filter);
    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}

/**
 * Get list of users in a given group
 * Effectively, this function is used twice: To get a list of all users currently
 * in the given group and to get all list of all users NOT in that group.
 *
 * @param    int     $group_id group id
 * @param    int     $listtype 1: return users not in the actual group, 2: return just inherited users
 * @return   string              option list containing uids and usernames
 */
function grp_selectUsers($group_id, $listtype = 0)
{
    global $_TABLES, $_USER;

    $retval = '';

    // Get a list of users in the Root Group and the selected group
    $sql = "SELECT DISTINCT uid FROM {$_TABLES['users']} LEFT JOIN {$_TABLES['group_assignments']} ";
    $sql .= "ON {$_TABLES['group_assignments']}.ug_uid = uid WHERE uid > 1 AND ";
    $sql .= "({$_TABLES['group_assignments']}.ug_main_grp_id = 1 OR {$_TABLES['group_assignments']}.ug_main_grp_id = $group_id)";
    $result = DB_query($sql);
    $filteredusers = array();
    while ($A = DB_fetchArray($result)) {
        $filteredusers[] = $A['uid'];
    }

    $groups = getGroupList($group_id);
    $grouplist = '(' . implode(',', $groups) . ')';
    $sql = "SELECT DISTINCT uid,username FROM {$_TABLES['users']} LEFT JOIN {$_TABLES['group_assignments']} ";
    $sql .= "ON {$_TABLES['group_assignments']}.ug_uid = uid WHERE uid > 1 AND ";
    $sql .= "{$_TABLES['group_assignments']}.ug_main_grp_id ";
    if ($listtype == 0) { // Group Users
        $sql .= " AND {$_TABLES['group_assignments']}.ug_main_grp_id = $group_id ";
    }    
    if ($listtype == 1) { // Available Users
        $sql .= "NOT IN {$grouplist} ";
        // Filter out the users that will be in the selected group
        $filteredusers = implode(',', $filteredusers);
        $sql .= " AND uid NOT IN ($filteredusers) ";
    }
    if ($listtype == 2) { // Inherited users
        $sql .= "IN {$grouplist} ";
        $sql .= " AND {$_TABLES['group_assignments']}.ug_main_grp_id != $group_id ";
    }
    $sql .= "ORDER BY username";
    
    $result = DB_query($sql);
    $numUsers = DB_numRows($result);
    for ($i = 0; $i < $numUsers; $i++) {
        list($uid, $username) = DB_fetchArray($result);
        $retval .= '<option';
        if ($listtype == 2) {
            $retval .= ' disabled';
        }
        $retval .= ' value="' . $uid . '">' . $username . '</option>';
    }

    return $retval;
}

/**
 * Allow easy addition/removal of users to/from a group
 *
 * @param    int $group Group ID
 * @return   string          HTML form
 */
function editusers($group)
{
    global $_CONF, $_TABLES, $_USER, $LANG_ACCESS, $LANG_ADMIN, $LANG28,
           $_IMAGE_TYPE, $_SCRIPTS;

    require_once $_CONF['path_system'] . 'lib-admin.php';

    $retval = '';

    $grp_name = DB_getItem($_TABLES['groups'], 'grp_name', "grp_id = $group");

    $thisUsersGroups = SEC_getUserGroups();
    $groupName = DB_getItem($_TABLES['groups'], 'grp_name', "grp_id='$group'");
    if ((!empty($group) && ($group > 0) &&
            !in_array($group, $thisUsersGroups) &&
            !SEC_groupIsRemoteUserAndHaveAccess($group, $thisUsersGroups))
        || (($grp_name == 'All Users') ||
            ($grp_name == 'Logged-in Users'))
    ) {
        if (!SEC_inGroup('Root') && ($grp_name == 'Root')) {
            $retval .= COM_showMessageText($LANG_ACCESS['canteditroot'],
                $LANG_ACCESS['usergroupadmin']);
            COM_accessLog("User {$_USER['username']} tried to edit the Root group with insufficient privileges.");
        } else {
            $retval .= COM_showMessageText($LANG_ACCESS['canteditgroup'],
                $LANG_ACCESS['usergroupadmin']);
        }

        return $retval;
    }

    $group_listing_url = $_CONF['site_admin_url'] . '/group.php';
    $showall = 0;
    if (Geeklog\Input::request('chk_showall') == 1) {
        $group_listing_url .= '?chk_showall=1';
        $showall = 1;
    }

    $menu_arr = array(
        array(
            'url'  => $group_listing_url,
            'text' => $LANG28[38],
        ),
        array(
            'url'  => $_CONF['site_admin_url'],
            'text' => $LANG_ADMIN['admin_home'],
        ),
    );

    $retval .= COM_startBlock($LANG_ACCESS['usergroupadmin'] . " - $groupName",
        '', COM_getBlockTemplate('_admin_block', 'header'));

    $retval .= ADMIN_createMenu($menu_arr, $LANG_ACCESS['editgroupmsg'],
        $_CONF['layout_url'] . '/images/icons/group.' . $_IMAGE_TYPE);
		
    // Add JavaScript
    // Hide the Advanced Editor as JavaScript is required. If JS is enabled then the JS below will un-hide it
    $js = 'document.getElementById("admin-groupmembers").style.display="";';
    $_SCRIPTS->setJavaScript($js, true);
	$_SCRIPTS->setJavaScriptFile('admin-groupmembers', '/javascript/moveusers.js');		

    $groupmembers = COM_newTemplate(CTL_core_templatePath($_CONF['path_layout'] . 'admin/group'));
    $groupmembers->set_file(array('groupmembers' => 'groupmembers.thtml'));
	$groupmembers->set_var('noscript', COM_getNoScript(false));
    $groupmembers->set_var('group_listing_url', $group_listing_url);
    $groupmembers->set_var('phpself', $_CONF['site_admin_url'] . '/group.php');
    $groupmembers->set_var('lang_adminhome', $LANG_ACCESS['adminhome']);
    $groupmembers->set_var('lang_instructions', $LANG_ACCESS['editgroupmsg']);
    $groupmembers->set_var('LANG_sitemembers', $LANG_ACCESS['availmembers']);
    $groupmembers->set_var('LANG_grpmembers', $LANG_ACCESS['groupmembers']);
    $groupmembers->set_var('LANG_inheritmembers', $LANG_ACCESS['inheritmembers']);
    $groupmembers->set_var('sitemembers', grp_selectUsers($group, 1));
    $groupmembers->set_var('group_list', grp_selectUsers($group));
    $groupmembers->set_var('inherit_list', grp_selectUsers($group, 2));
    $groupmembers->set_var('LANG_add', $LANG_ACCESS['add']);
    $groupmembers->set_var('LANG_remove', $LANG_ACCESS['remove']);
    $groupmembers->set_var('lang_save', $LANG_ADMIN['save']);
    $groupmembers->set_var('lang_cancel', $LANG_ADMIN['cancel']);
    $groupmembers->set_var('lang_grouplist', $LANG28[38]);
    $groupmembers->set_var('show_all', $showall);
    $groupmembers->set_var('group_id', $group);
    $groupmembers->set_var('gltoken_name', CSRF_TOKEN);
    $groupmembers->set_var('gltoken', SEC_createToken());
    $groupmembers->parse('output', 'groupmembers');
    $retval .= $groupmembers->finish($groupmembers->get_var('output'));

    $retval .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));

    return $retval;
}

/**
 * Save changes from the form to add/remove users to/from groups
 *
 * @param    int    $groupid      id of the group being changed
 * @param    string $groupmembers list of group members
 */
function savegroupusers($groupid, $groupmembers)
{
    global $_CONF, $_TABLES;

    $retval = '';

    $updateUsers = explode("|", $groupmembers);
    $updateCount = count($updateUsers);
    if ($updateCount > 0) {

        // Retrieve all existing users in group so we can determine if changes
        // are needed
        $activeUsers = array();
        $query = DB_query("SELECT ug_uid FROM {$_TABLES['group_assignments']} WHERE ug_main_grp_id = $groupid");
        if (DB_numRows($query) > 0) {
            while ($A = DB_fetchArray($query, false)) {
                array_push($activeUsers, $A['ug_uid']);
            }
            $deleteGroupUsers = array_diff($activeUsers, $updateUsers);
            $addGroupUsers = array_diff($updateUsers, $activeUsers);
            if (is_array($deleteGroupUsers) AND count($deleteGroupUsers) > 0) {
                foreach ($deleteGroupUsers as $uid) {
                    $uid = COM_applyFilter($uid, true);
                    DB_query("DELETE FROM {$_TABLES['group_assignments']} WHERE ug_main_grp_id = $groupid AND ug_uid = $uid");
                }
            }
            if (is_array($addGroupUsers) AND count($addGroupUsers) > 0) {
                foreach ($addGroupUsers as $uid) {
                    $uid = COM_applyFilter($uid, true);
                    DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_uid) VALUES ('$groupid', $uid)");
                }
            }

        } else {

            // No active users which should never occur as Root users
            // are always members
            for ($i = 0; $i < $updateCount; $i++) {
                $updateUsers[$i] = COM_applyFilter($updateUsers[$i], true);
                DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_uid) VALUES ('$groupid', '$updateUsers[$i]')");
            }

        }

    }

    if (Geeklog\Input::request('chk_showall') == 1) {
        COM_redirect($_CONF['site_admin_url'] . '/group.php?msg=49&chk_showall=1');
    } else {
        COM_redirect($_CONF['site_admin_url'] . '/group.php?msg=49');
    }
}

/**
 * Delete a group
 *
 * @param    int $grp_id id of group to delete
 * @return   string              HTML redirect
 */
function deleteGroup($grp_id)
{
    global $_CONF, $_TABLES, $_USER;

    if (!SEC_inGroup('Root') && (DB_getItem($_TABLES['groups'], 'grp_name',
                "grp_id = $grp_id") == 'Root')
    ) {
        COM_accessLog("User {$_USER['username']} tried to delete the Root group with insufficient privileges.");
        COM_redirect($_CONF['site_admin_url'] . '/group.php');
    }

    $GroupAdminGroups = SEC_getUserGroups();
    if (!in_array($grp_id, $GroupAdminGroups) && !SEC_groupIsRemoteUserAndHaveAccess($grp_id, $GroupAdminGroups)) {
        COM_accessLog("User {$_USER['username']} tried to delete group $grp_id with insufficient privileges.");
        COM_redirect($_CONF['site_admin_url'] . '/group.php');
    }

    DB_delete($_TABLES['access'], 'acc_grp_id', $grp_id);
    DB_delete($_TABLES['group_assignments'], 'ug_grp_id', $grp_id);
    DB_delete($_TABLES['group_assignments'], 'ug_main_grp_id', $grp_id);
    DB_delete($_TABLES['groups'], 'grp_id', $grp_id);

    PLG_groupChanged($grp_id, 'delete');
    if (Geeklog\Input::request('chk_showall') == 1) {
        COM_redirect($_CONF['site_admin_url'] . '/group.php?msg=50&chk_showall=1');
    } else {
        COM_redirect($_CONF['site_admin_url'] . '/group.php?msg=50');
    }
}

// MAIN
$mode = Geeklog\Input::request('mode', '');

if (($mode == $LANG_ADMIN['delete']) && !empty($LANG_ADMIN['delete'])) {
    $grp_id = (int) Geeklog\Input::fRequest('grp_id');
    if (!isset ($grp_id) || empty($grp_id) || ($grp_id == 0)) {
        COM_errorLog('Attempted to delete group grp_id=' . $grp_id);
        COM_redirect($_CONF['site_admin_url'] . '/group.php');
    } elseif (SEC_checkToken()) {
        $display .= deleteGroup($grp_id);
    } else {
        COM_accessLog("User {$_USER['username']} tried to illegally delete group $grp_id and failed CSRF checks.");
        COM_redirect($_CONF['site_admin_url'] . '/index.php');
    }
} elseif (($mode == $LANG_ADMIN['save']) && !empty($LANG_ADMIN['save']) && SEC_checkToken()) {
    $grp_gl_core = (int) Geeklog\Input::fPost('grp_gl_core', 0);
    $grp_default = isset($_POST['chk_grpdefault']) ? 1 : 0;
    $grp_applydefault = isset($_POST['chk_applydefault']) ? 1 : 0;
    $chk_grpadmin = Geeklog\Input::fPost('chk_grpadmin', '');
    $features = Geeklog\Input::post('features', array());
    $groups = Geeklog\Input::post('groups', array());
    $display .= savegroup(
        (int) Geeklog\Input::fPost('grp_id', 0),
        Geeklog\Input::fPost('grp_name'),
        Geeklog\Input::post('grp_descr'),
        $chk_grpadmin, $grp_gl_core, $grp_default, $grp_applydefault, $features, $groups
    );
} elseif (($mode === 'savegroupusers') && SEC_checkToken()) {
    $grp_id = (int) Geeklog\Input::fRequest('grp_id', 0);
    $display .= savegroupusers($grp_id, $_POST['groupmembers']);
} elseif ($mode === 'edit') {
    $grp_id = (int) Geeklog\Input::fRequest('grp_id', 0);
    $display .= editgroup($grp_id);
    $display = COM_createHTMLDocument($display, array('pagetitle' => $LANG_ACCESS['groupeditor']));
} elseif ($mode === 'listusers') {
    $grp_id = (int) Geeklog\Input::fRequest('grp_id', 0);
    $display .= listusers($grp_id);
    $display = COM_createHTMLDocument($display, array('pagetitle' => $LANG_ACCESS['groupmembers']));
} elseif ($mode === 'editusers') {
    $grp_id = (int) Geeklog\Input::fRequest('grp_id', 0);
    $display .= editusers($grp_id);
    $display = COM_createHTMLDocument($display, array('pagetitle' => $LANG_ACCESS['usergroupadmin']));
} else { // 'cancel' or no mode at all
    $show_all_groups = false;
    if (isset($_POST['q'])) {
        // check $_POST only, as $_GET['chk_showall'] may also be set
        if (Geeklog\Input::post('chk_showall') == 1) {
            $show_all_groups = true;
        }
    } elseif (Geeklog\Input::request('chk_showall') == 1) {
        $show_all_groups = true;
    }
    $display .= COM_showMessageFromParameter();
    $display .= listgroups($show_all_groups);
    $display = COM_createHTMLDocument($display, array('pagetitle' => $LANG28[38]));
}

COM_output($display);
