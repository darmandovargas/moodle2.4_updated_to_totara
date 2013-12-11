<?php

    require_once('../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->dirroot.'/user/filters/lib.php');

    $delete       = optional_param('delete', 0, PARAM_INT);
    $confirm      = optional_param('confirm', '', PARAM_ALPHANUM);   //md5 confirmation hash
    $confirmuser  = optional_param('confirmuser', 0, PARAM_INT);
    $sort         = optional_param('sort', 'name', PARAM_ALPHANUM);
    $dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
    $page         = optional_param('page', 0, PARAM_INT);
    $perpage      = optional_param('perpage', 30, PARAM_INT);        // how many per page
    $ru           = optional_param('ru', '2', PARAM_INT);            // show remote users
    $lu           = optional_param('lu', '2', PARAM_INT);            // show local users
    $acl          = optional_param('acl', '0', PARAM_INT);           // id of user to tweak mnet ACL (requires $access)
    $suspend      = optional_param('suspend', 0, PARAM_INT);
    $unsuspend    = optional_param('unsuspend', 0, PARAM_INT);

    admin_externalpage_setup('editusers');

    $sitecontext = context_system::instance();
    $site = get_site();

    if (!has_capability('moodle/user:update', $sitecontext) and !has_capability('moodle/user:delete', $sitecontext)) {
        print_error('nopermissions', 'error', '', 'edit/delete users');
    }

    $stredit   = get_string('edit');
    $strdelete = get_string('delete');
    $strdeletecheck = get_string('deletecheck');
    $strshowallusers = get_string('showallusers');
    $strsuspend = get_string('suspenduser', 'admin');
    $strunsuspend = get_string('unsuspenduser', 'admin');
    $strconfirm = get_string('confirm');

    if (empty($CFG->loginhttps)) {
        $securewwwroot = $CFG->wwwroot;
    } else {
        $securewwwroot = str_replace('http:','https:',$CFG->wwwroot);
    }

    $returnurl = new moodle_url('/admin/user.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage, 'page'=>$page));

    if ($confirmuser and confirm_sesskey()) {
        require_capability('moodle/user:update', $sitecontext);
        if (!$user = $DB->get_record('user', array('id'=>$confirmuser, 'mnethostid'=>$CFG->mnet_localhost_id))) {
            print_error('nousers');
        }

        $auth = get_auth_plugin($user->auth);

        $result = $auth->user_confirm($user->username, $user->secret);

        if ($result == AUTH_CONFIRM_OK or $result == AUTH_CONFIRM_ALREADY) {
            redirect($returnurl);
        } else {
            echo $OUTPUT->header();
            redirect($returnurl, get_string('usernotconfirmed', '', fullname($user, true)));
        }

    } else if ($delete and confirm_sesskey()) {              // Delete a selected user, after confirmation
        require_capability('moodle/user:delete', $sitecontext);

        $user = $DB->get_record('user', array('id'=>$delete, 'mnethostid'=>$CFG->mnet_localhost_id), '*', MUST_EXIST);

        if (is_siteadmin($user->id)) {
            print_error('useradminodelete', 'error');
        }

        if ($confirm != md5($delete)) {
            echo $OUTPUT->header();
            $fullname = fullname($user, true);
            echo $OUTPUT->heading(get_string('deleteuser', 'admin'));
            $optionsyes = array('delete'=>$delete, 'confirm'=>md5($delete), 'sesskey'=>sesskey());
            echo $OUTPUT->confirm(get_string('deletecheckfull', '', "'$fullname'"), new moodle_url($returnurl, $optionsyes), $returnurl);
            echo $OUTPUT->footer();
            die;
        } else if (data_submitted() and !$user->deleted) {
            if (delete_user($user)) {
                session_gc(); // remove stale sessions
                redirect($returnurl);
            } else {
                session_gc(); // remove stale sessions
                echo $OUTPUT->header();
                echo $OUTPUT->notification($returnurl, get_string('deletednot', '', fullname($user, true)));
            }
        }
    } else if ($acl and confirm_sesskey()) {
        if (!has_capability('moodle/user:update', $sitecontext)) {
            print_error('nopermissions', 'error', '', 'modify the NMET access control list');
        }
        if (!$user = $DB->get_record('user', array('id'=>$acl))) {
            print_error('nousers', 'error');
        }
        if (!is_mnet_remote_user($user)) {
            print_error('usermustbemnet', 'error');
        }
        $accessctrl = strtolower(required_param('accessctrl', PARAM_ALPHA));
        if ($accessctrl != 'allow' and $accessctrl != 'deny') {
            print_error('invalidaccessparameter', 'error');
        }
        $aclrecord = $DB->get_record('mnet_sso_access_control', array('username'=>$user->username, 'mnet_host_id'=>$user->mnethostid));
        if (empty($aclrecord)) {
            $aclrecord = new stdClass();
            $aclrecord->mnet_host_id = $user->mnethostid;
            $aclrecord->username = $user->username;
            $aclrecord->accessctrl = $accessctrl;
            $DB->insert_record('mnet_sso_access_control', $aclrecord);
        } else {
            $aclrecord->accessctrl = $accessctrl;
            $DB->update_record('mnet_sso_access_control', $aclrecord);
        }
        $mnethosts = $DB->get_records('mnet_host', null, 'id', 'id,wwwroot,name');
        redirect($returnurl);

    } else if ($suspend and confirm_sesskey()) {
        require_capability('moodle/user:update', $sitecontext);

        if ($user = $DB->get_record('user', array('id'=>$suspend, 'mnethostid'=>$CFG->mnet_localhost_id, 'deleted'=>0))) {
            if (!is_siteadmin($user) and $USER->id != $user->id and $user->suspended != 1) {
                $user->suspended = 1;
                $user->timemodified = time();
                $DB->set_field('user', 'suspended', $user->suspended, array('id'=>$user->id));
                $DB->set_field('user', 'timemodified', $user->timemodified, array('id'=>$user->id));
                // force logout
                session_kill_user($user->id);
                events_trigger('user_updated', $user);
            }
        }
        redirect($returnurl);

    } else if ($unsuspend and confirm_sesskey()) {
        require_capability('moodle/user:update', $sitecontext);

        if ($user = $DB->get_record('user', array('id'=>$unsuspend, 'mnethostid'=>$CFG->mnet_localhost_id, 'deleted'=>0))) {
            if ($user->suspended != 0) {
                $user->suspended = 0;
                $user->timemodified = time();
                $DB->set_field('user', 'suspended', $user->suspended, array('id'=>$user->id));
                $DB->set_field('user', 'timemodified', $user->timemodified, array('id'=>$user->id));
                events_trigger('user_updated', $user);
            }
        }
        redirect($returnurl);
    }

    // create the user filter form
    $ufiltering = new user_filtering();
    echo $OUTPUT->header();

    // Carry on with the user listing
    $context = context_system::instance();
    $extracolumns = get_extra_user_fields($context);
    $columns = array_merge(array('firstname', 'lastname'), $extracolumns,
            array('city', 'country', 'lastaccess'));

    foreach ($columns as $column) {
        $string[$column] = get_user_field_name($column);
        if ($sort != $column) {
            $columnicon = "";
            if ($column == "lastaccess") {
                $columndir = "DESC";
            } else {
                $columndir = "ASC";
            }
        } else {
            $columndir = $dir == "ASC" ? "DESC":"ASC";
            if ($column == "lastaccess") {
                $columnicon = ($dir == "ASC") ? "sort_desc" : "sort_asc";
            } else {
                $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
            }
            $columnicon = "<img class='iconsort' src=\"" . $OUTPUT->pix_url('t/' . $columnicon) . "\" alt=\"\" />";

        }
        $$column = "<a href=\"user.php?sort=$column&amp;dir=$columndir\">".$string[$column]."</a>$columnicon";
    }

    $override = new stdClass();
    $override->firstname = 'firstname';
    $override->lastname = 'lastname';
    $fullnamelanguage = get_string('fullnamedisplay', '', $override);
    if (($CFG->fullnamedisplay == 'firstname lastname') or
        ($CFG->fullnamedisplay == 'firstname') or
        ($CFG->fullnamedisplay == 'language' and $fullnamelanguage == 'firstname lastname' )) {
        $fullnamedisplay = "$firstname / $lastname";
        if ($sort == "name") { // If sort has already been set to something else then ignore.
            $sort = "firstname";
        }
    } else { // ($CFG->fullnamedisplay == 'language' and $fullnamelanguage == 'lastname firstname').
        $fullnamedisplay = "$lastname / $firstname";
        if ($sort == "name") { // This should give the desired sorting based on fullnamedisplay.
            $sort = "lastname";
        }
    }

    list($extrasql, $params) = $ufiltering->get_sql_filter();

    $usercount = get_users(false);
    $usersearchcount = get_users(false, '', false, null, "", '', '', '', '', '*', $extrasql, $params);

    // Exclude guest user from list.
    $noguestsql = '';
    if (!empty($extrasql)) {
        $noguestsql .= ' AND';
    }
    $noguestsql .= " id <> :guestid";
    $params['guestid'] = $CFG->siteguest;
    $users = get_users_listing($sort, $dir, $page*$perpage, $perpage, '', '', '',
            $extrasql.$noguestsql, $params, $context);

    if ($extrasql !== '') {
        echo $OUTPUT->heading("$usersearchcount / $usercount ".get_string('users'));
        $usercount = $usersearchcount;
    } else {
        //echo $OUTPUT->heading("$usercount ".get_string('users'));
		echo html_writer::tag("legend", "User's List", array("style"=>"color:#285582; font-size:150%; font-weight:bold; margin-bottom:30px"));
    }

    $strall = get_string('all');

    $baseurl = new moodle_url('/admin/user.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage));
    echo $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);

    flush();


    if (!$users) {
        $match = array();
        echo $OUTPUT->heading(get_string('nousersfound'));

        $table = NULL;

    } else {

        $countries = get_string_manager()->get_list_of_countries(false);
        if (empty($mnethosts)) {
            $mnethosts = $DB->get_records('mnet_host', null, 'id', 'id,wwwroot,name');
        }

        foreach ($users as $key => $user) {
            if (isset($countries[$user->country])) {
                $users[$key]->country = $countries[$user->country];
            }
        }
        if ($sort == "country") {  // Need to resort by full country name, not code
            foreach ($users as $user) {
                $susers[$user->id] = $user->country;
            }
            asort($susers);
            foreach ($susers as $key => $value) {
                $nusers[] = $users[$key];
            }
            $users = $nusers;
        }

        $table = new html_table();
        $table->head = array ();
        $table->align = array();
       // $table->head[] = $fullnamedisplay;
	    $table->head[] = get_string('username');
	    $table->head[] = $lastname;
		$table->align[] = 'left';
		$table->head[] = $firstname;
        $table->align[] = 'left';
        foreach ($extracolumns as $field) {
            $table->head[] = ${$field};
            $table->align[] = 'left';
        }
        $table->head[] = $city;
        $table->align[] = 'left';
        $table->head[] = $country;
        $table->align[] = 'left';
        $table->head[] = $lastaccess;
        $table->align[] = 'left';
		$table->head[] = get_string('status');
        $table->align[] = 'left';
        $table->head[] = get_string('edit');
        $table->align[] = 'center';
        $table->head[] = "";
        $table->align[] = 'center';

        $table->width = "95%";
        foreach ($users as $user) {
            if (isguestuser($user)) {
                continue; // do not display guest here
            }

            $buttons = array();
            $lastcolumn = '';

            // delete button
            if (has_capability('moodle/user:delete', $sitecontext)) {
                if (is_mnet_remote_user($user) or $user->id == $USER->id or is_siteadmin($user)) {
                    // no deleting of self, mnet accounts or admins allowed
                } else {
                    $buttons[] = html_writer::link(new moodle_url($returnurl, array('delete'=>$user->id, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/delete'), 'alt'=>$strdelete, 'class'=>'iconsmall')), array('title'=>$strdelete));
                }
            }

            // suspend button
            if (has_capability('moodle/user:update', $sitecontext)) {
                if (is_mnet_remote_user($user)) {
                    // mnet users have special access control, they can not be deleted the standard way or suspended
                    $accessctrl = 'allow';
                    if ($acl = $DB->get_record('mnet_sso_access_control', array('username'=>$user->username, 'mnet_host_id'=>$user->mnethostid))) {
                        $accessctrl = $acl->accessctrl;
                    }
                    $changeaccessto = ($accessctrl == 'deny' ? 'allow' : 'deny');
                    $buttons[] = " (<a href=\"?acl={$user->id}&amp;accessctrl=$changeaccessto&amp;sesskey=".sesskey()."\">".get_string($changeaccessto, 'mnet') . " access</a>)";

                } else {
                    if ($user->suspended) {
                        $buttons[] = html_writer::link(new moodle_url($returnurl, array('unsuspend'=>$user->id, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/show'), 'alt'=>$strunsuspend, 'class'=>'iconsmall')), array('title'=>$strunsuspend));
                    } else {
                        if ($user->id == $USER->id or is_siteadmin($user)) {
                            // no suspending of admins or self!
                        } else {
                            $buttons[] = html_writer::link(new moodle_url($returnurl, array('suspend'=>$user->id, 'sesskey'=>sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/hide'), 'alt'=>$strsuspend, 'class'=>'iconsmall')), array('title'=>$strsuspend));
                        }
                    }

                }
            }

            // edit button
            if (has_capability('moodle/user:update', $sitecontext)) {
                // prevent editing of admins by non-admins
                if (is_siteadmin($USER) or !is_siteadmin($user)) {
                    $buttons[] = html_writer::link(new moodle_url($securewwwroot.'/user/editadvanced.php', array('id'=>$user->id, 'course'=>$site->id)), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/edit'), 'alt'=>$stredit, 'class'=>'iconsmall')), array('title'=>$stredit));
                }
            }

            // the last column - confirm or mnet info
            if (is_mnet_remote_user($user)) {
                // all mnet users are confirmed, let's print just the name of the host there
                if (isset($mnethosts[$user->mnethostid])) {
                    $lastcolumn = get_string($accessctrl, 'mnet').': '.$mnethosts[$user->mnethostid]->name;
                } else {
                    $lastcolumn = get_string($accessctrl, 'mnet');
                }

            } else if ($user->confirmed == 0) {
                if (has_capability('moodle/user:update', $sitecontext)) {
                    $lastcolumn = html_writer::link(new moodle_url($returnurl, array('confirmuser'=>$user->id, 'sesskey'=>sesskey())), $strconfirm);
                } else {
                    $lastcolumn = "<span class=\"dimmed_text\">".get_string('confirm')."</span>";
                }
            }

            if ($user->lastaccess) {
                $strlastaccess = format_time(time() - $user->lastaccess);
            } else {
                $strlastaccess = get_string('never');
            }
            $fullname = fullname($user, true);

            $row = array ();
			//print_r($user);
			$row[] = $user->username;
			$row[] = $user->lastname;
			$row[] = $user->firstname;
            //$row[] = "<a href=\"../user/view.php?id=$user->id&amp;course=$site->id\">$fullname</a>";
            foreach ($extracolumns as $field) {
				$row[] = $user->{$field};
            }
            $row[] = $user->city;
            $row[] = $user->country;
            $row[] = $strlastaccess;
			if($user->suspended)
				$row[]= get_string('locked');
			else if(!$user->suspended)
				$row[] = get_string('active');
			else
				$row[] = get_string('inactive');
			if ($user->suspended) {
                foreach ($row as $k=>$v) {
                    $row[$k] = html_writer::tag('span', $v, array('class'=>'usersuspended'));
                }
            }
			
            $row[] = implode(' ', $buttons);
            $row[] = $lastcolumn;
            $table->data[] = $row;
        }
    }

    // add filters
    $ufiltering->display_add();
    $ufiltering->display_active();
		echo html_writer::start_tag("div",array("style"=>"width:95%; height:60px; border-top-left-radius:8px; border-top-right-radius:8px; background-color:#53585C", "id"=>"form_astm"));
		echo html_writer::start_tag("form", array("method"=>"post", "action"=>$CFG->wwwroot ."/admin/user_astm.php", "accept-charset"=>"utf-8", "autocomplete"=>"off", "id"=>"mform"));
		echo html_writer::start_tag("div", array("style"=>"float:left"));
		echo html_writer::tag ("input","",array("type"=>"text", "style"=>"background-color: #FFFFFF; height:25px; width: 130px; margin-top:15px; margin-left:10px; color: gray", "placeholder"=>"Last Name", "id"=>"id_lastname", "name"=>"lastname"));
		echo html_writer::end_tag("div");
		echo html_writer::start_tag("div", array("style"=>"float:left"));
		echo html_writer::tag ("input","",array("type"=>"text", "style"=>"background-color: #FFFFFF; height:25px; width: 130px; margin-top:15px; margin-left:10px; color: gray", "placeholder"=>"First Name", "id"=>"id_firstname", "name"=>"firstname"));
		echo html_writer::end_tag("div");
		echo html_writer::start_tag("div", array("style"=>"float:left"));
		echo html_writer::tag ("input","",array("type"=>"text", "style"=>"background-color: #FFFFFF; height:25px; width: 130px; ; margin-top:15px; margin-left:10px; color: gray", "placeholder"=>"City/town", "id"=>"id_city", "name"=>"city"));
		echo html_writer::end_tag("div");
		echo html_writer::start_tag("div", array("style"=>"float:left"));
		echo html_writer::tag ("input","",array("type"=>"text", "style"=>"background-color: #FFFFFF; height:25px; width: 130px; ; margin-top:15px; margin-left:10px; color: gray", "placeholder"=>"Username", "id"=>"id_username", "name"=>"username"));
		echo html_writer::tag ("select","<option value='AF'>Afghanistan</option>
										<option value='AX'>Åland Islands</option>
										<option value='AL'>Albania</option>
										<option value='DZ'>Algeria</option>
										<option value='AS'>American Samoa</option>
										<option value='AD'>Andorra</option>
										<option value='AO'>Angola</option>
										<option value='AI'>Anguilla</option>
										<option value='AQ'>Antarctica</option>
										<option value='AG'>Antigua And Barbuda</option>
										<option value='AR'>Argentina</option>
										<option value='AM'>Armenia</option>
										<option value='AW'>Aruba</option>
										<option value='AU'>Australia</option>
										<option value='AT'>Austria</option>
										<option value='AZ'>Azerbaijan</option>
										<option value='BS'>Bahamas</option>
										<option value='BH'>Bahrain</option>
										<option value='BD'>Bangladesh</option>
										<option value='BB'>Barbados</option>
										<option value='BY'>Belarus</option>
										<option value='BE'>Belgium</option>
										<option value='BZ'>Belize</option>
										<option value='BJ'>Benin</option>
										<option value='BM'>Bermuda</option>
										<option value='BT'>Bhutan</option>
										<option value='BO'>Bolivia, Plurinational State Of</option>
										<option value='BQ'>Bonaire, Sint Eustatius And Saba</option>
										<option value='BA'>Bosnia And Herzegovina</option>
										<option value='BW'>Botswana</option>
										<option value='BV'>Bouvet Island</option>
										<option value='BR'>Brazil</option>
										<option value='IO'>British Indian Ocean Territory</option>
										<option value='BN'>Brunei Darussalam</option>
										<option value='BG'>Bulgaria</option>
										<option value='BF'>Burkina Faso</option>
										<option value='BI'>Burundi</option>
										<option value='KH'>Cambodia</option>
										<option value='CM'>Cameroon</option>
										<option value='CA'>Canada</option>
										<option value='CV'>Cape Verde</option>
										<option value='KY'>Cayman Islands</option>
										<option value='CF'>Central African Republic</option>
										<option value='TD'>Chad</option>
										<option value='CL'>Chile</option>
										<option value='CN'>China</option>
										<option value='CX'>Christmas Island</option>
										<option value='CC'>Cocos (Keeling) Islands</option>
										<option value='CO'>Colombia</option>
										<option value='KM'>Comoros</option>
										<option value='CG'>Congo</option>
										<option value='CD'>Congo, The Democratic Republic Of The</option>
										<option value='CK'>Cook Islands</option>
										<option value='CR'>Costa Rica</option>
										<option value='CI'>Côte D'Ivoire</option>
										<option value='HR'>Croatia</option>
										<option value='CU'>Cuba</option>
										<option value='CW'>Curaçao</option>
										<option value='CY'>Cyprus</option>
										<option value='CZ'>Czech Republic</option>
										<option value='DK'>Denmark</option>
										<option value='DJ'>Djibouti</option>
										<option value='DM'>Dominica</option>
										<option value='DO'>Dominican Republic</option>
										<option value='EC'>Ecuador</option>
										<option value='EG'>Egypt</option>
										<option value='SV'>El Salvador</option>
										<option value='GQ'>Equatorial Guinea</option>
										<option value='ER'>Eritrea</option>
										<option value='EE'>Estonia</option>
										<option value='ET'>Ethiopia</option>
										<option value='FK'>Falkland Islands (Malvinas)</option>
										<option value='FO'>Faroe Islands</option>
										<option value='FJ'>Fiji</option>
										<option value='FI'>Finland</option>
										<option value='FR'>France</option>
										<option value='GF'>French Guiana</option>
										<option value='PF'>French Polynesia</option>
										<option value='TF'>French Southern Territories</option>
										<option value='GA'>Gabon</option>
										<option value='GM'>Gambia</option>
										<option value='GE'>Georgia</option>
										<option value='DE'>Germany</option>
										<option value='GH'>Ghana</option>
										<option value='GI'>Gibraltar</option>
										<option value='GR'>Greece</option>
										<option value='GL'>Greenland</option>
										<option value='GD'>Grenada</option>
										<option value='GP'>Guadeloupe</option>
										<option value='GU'>Guam</option>
										<option value='GT'>Guatemala</option>
										<option value='GG'>Guernsey</option>
										<option value='GN'>Guinea</option>
										<option value='GW'>Guinea-Bissau</option>
										<option value='GY'>Guyana</option>
										<option value='HT'>Haiti</option>
										<option value='HM'>Heard Island And Mcdonald Islands</option>
										<option value='VA'>Holy See (Vatican City State)</option>
										<option value='HN'>Honduras</option>
										<option value='HK'>Hong Kong</option>
										<option value='HU'>Hungary</option>
										<option value='IS'>Iceland</option>
										<option value='IN'>India</option>
										<option value='ID'>Indonesia</option>
										<option value='IR'>Iran, Islamic Republic Of</option>
										<option value='IQ'>Iraq</option>
										<option value='IE'>Ireland</option>
										<option value='IM'>Isle Of Man</option>
										<option value='IL'>Israel</option>
										<option value='IT'>Italy</option>
										<option value='JM'>Jamaica</option>
										<option value='JP'>Japan</option>
										<option value='JE'>Jersey</option>
										<option value='JO'>Jordan</option>
										<option value='KZ'>Kazakhstan</option>
										<option value='KE'>Kenya</option>
										<option value='KI'>Kiribati</option>
										<option value='KP'>Korea, Democratic People's Republic Of</option>
										<option value='KR'>Korea, Republic Of</option>
										<option value='KW'>Kuwait</option>
										<option value='KG'>Kyrgyzstan</option>
										<option value='LA'>Lao People's Democratic Republic</option>
										<option value='LV'>Latvia</option>
										<option value='LB'>Lebanon</option>
										<option value='LS'>Lesotho</option>
										<option value='LR'>Liberia</option>
										<option value='LY'>Libya</option>
										<option value='LI'>Liechtenstein</option>
										<option value='LT'>Lithuania</option>
										<option value='LU'>Luxembourg</option>
										<option value='MO'>Macao</option>
										<option value='MK'>Macedonia, The Former Yugoslav Republic Of</option>
										<option value='MG'>Madagascar</option>
										<option value='MW'>Malawi</option>
										<option value='MY'>Malaysia</option>
										<option value='MV'>Maldives</option>
										<option value='ML'>Mali</option>
										<option value='MT'>Malta</option>
										<option value='MH'>Marshall Islands</option>
										<option value='MQ'>Martinique</option>
										<option value='MR'>Mauritania</option>
										<option value='MU'>Mauritius</option>
										<option value='YT'>Mayotte</option>
										<option value='MX'>Mexico</option>
										<option value='FM'>Micronesia, Federated States Of</option>
										<option value='MD'>Moldova, Republic Of</option>
										<option value='MC'>Monaco</option>
										<option value='MN'>Mongolia</option>
										<option value='ME'>Montenegro</option>
										<option value='MS'>Montserrat</option>
										<option value='MA'>Morocco</option>
										<option value='MZ'>Mozambique</option>
										<option value='MM'>Myanmar</option>
										<option value='NA'>Namibia</option>
										<option value='NR'>Nauru</option>
										<option value='NP'>Nepal</option>
										<option value='NL'>Netherlands</option>
										<option value='AN'>Netherlands Antilles</option>
										<option value='NC'>New Caledonia</option>
										<option value='NZ'>New Zealand</option>
										<option value='NI'>Nicaragua</option>
										<option value='NE'>Niger</option>
										<option value='NG'>Nigeria</option>
										<option value='NU'>Niue</option>
										<option value='NF'>Norfolk Island</option>
										<option value='MP'>Northern Mariana Islands</option>
										<option value='NO'>Norway</option>
										<option value='OM'>Oman</option>
										<option value='PK'>Pakistan</option>
										<option value='PW'>Palau</option>
										<option value='PS'>Palestine, State Of</option>
										<option value='PA'>Panama</option>
										<option value='PG'>Papua New Guinea</option>
										<option value='PY'>Paraguay</option>
										<option value='PE'>Peru</option>
										<option value='PH'>Philippines</option>
										<option value='PN'>Pitcairn</option>
										<option value='PL'>Poland</option>
										<option value='PT'>Portugal</option>
										<option value='PR'>Puerto Rico</option>
										<option value='QA'>Qatar</option>
										<option value='RE'>Réunion</option>
										<option value='RO'>Romania</option>
										<option value='RU'>Russian Federation</option>
										<option value='RW'>Rwanda</option>
										<option value='BL'>Saint Barthélemy</option>
										<option value='SH'>Saint Helena, Ascension And Tristan Da Cunha</option>
										<option value='KN'>Saint Kitts And Nevis</option>
										<option value='LC'>Saint Lucia</option>
										<option value='MF'>Saint Martin (French Part)</option>
										<option value='PM'>Saint Pierre And Miquelon</option>
										<option value='VC'>Saint Vincent And The Grenadines</option>
										<option value='WS'>Samoa</option>
										<option value='SM'>San Marino</option>
										<option value='ST'>Sao Tome And Principe</option>
										<option value='SA'>Saudi Arabia</option>
										<option value='SN'>Senegal</option>
										<option value='RS'>Serbia</option>
										<option value='SC'>Seychelles</option>
										<option value='SL'>Sierra Leone</option>
										<option value='SG'>Singapore</option>
										<option value='SX'>Sint Maarten (Dutch Part)</option>
										<option value='SK'>Slovakia</option>
										<option value='SI'>Slovenia</option>
										<option value='SB'>Solomon Islands</option>
										<option value='SO'>Somalia</option>
										<option value='ZA'>South Africa</option>
										<option value='GS'>South Georgia And The South Sandwich Islands</option>
										<option value='SS'>South Sudan</option>
										<option value='ES'>Spain</option>
										<option value='LK'>Sri Lanka</option>
										<option value='SD'>Sudan</option>
										<option value='SR'>Suriname</option>
										<option value='SJ'>Svalbard And Jan Mayen</option>
										<option value='SZ'>Swaziland</option>
										<option value='SE'>Sweden</option>
										<option value='CH'>Switzerland</option>
										<option value='SY'>Syrian Arab Republic</option>
										<option value='TW'>Taiwan</option>
										<option value='TJ'>Tajikistan</option>
										<option value='TZ'>Tanzania, United Republic Of</option>
										<option value='TH'>Thailand</option>
										<option value='TL'>Timor-Leste</option>
										<option value='TG'>Togo</option>
										<option value='TK'>Tokelau</option>
										<option value='TO'>Tonga</option>
										<option value='TT'>Trinidad And Tobago</option>
										<option value='TN'>Tunisia</option>
										<option value='TR'>Turkey</option>
										<option value='TM'>Turkmenistan</option>
										<option value='TC'>Turks And Caicos Islands</option>
										<option value='TV'>Tuvalu</option>
										<option value='UG'>Uganda</option>
										<option value='UA'>Ukraine</option>
										<option value='AE'>United Arab Emirates</option>
										<option value='GB'>United Kingdom</option>
										<option value='US' selected='selected'>United States</option>
										<option value='UM'>United States Minor Outlying Islands</option>
										<option value='UY'>Uruguay</option>
										<option value='UZ'>Uzbekistan</option>
										<option value='VU'>Vanuatu</option>
										<option value='VE'>Venezuela, Bolivarian Republic Of</option>
										<option value='VN'>Viet Nam</option>
										<option value='VG'>Virgin Islands, British</option>
										<option value='VI'>Virgin Islands, U.S.</option>
										<option value='WF'>Wallis And Futuna</option>
										<option value='EH'>Western Sahara</option>
										<option value='YE'>Yemen</option>
										<option value='ZM'>Zambia</option>
										<option value='ZW'>Zimbabwe</option>",array("style"=>"background-color: #FFFFFF; height:34px; width: 100%; color: gray; display:none", "name"=>"country", "id"=>"id_country"));
		echo html_writer::end_tag("div");
		echo html_writer::start_tag("div", array("style"=>"float:left"));
		echo html_writer::tag ("input","",array("type"=>"text", "style"=>"background-color: #FFFFFF; height:25px; width: 130px; margin-top:15px; margin-left:10px; color: gray", "placeholder"=>"Email address", "id"=>"id_email", "name"=>"email"));
		echo html_writer::end_tag("div");
		echo html_writer::start_tag("div", array("style"=>"float:left;"));
		echo html_writer::tag("input","", array("type"=>"submit","style"=>"background-color:#FECA30; height:33px; width: 90px; margin-top:15px; margin-left:10px; color: gray", "name"=>"addfilter", "id"=>"id_addfilter", "value"=>"SEARCH"));
		echo html_writer::end_tag("div");
		echo "<div style='display: none;'><input type='hidden' value='1' name='mform_showadvanced_last'>
<input type='hidden'  name='sesskey' id='sesskey'>
<input type='hidden' value='1' name='_qf__user_add_filter_form'>
</div>
		
		<div class='fcontainer clearfix' style='display:none'>
		
		<div class='fitem fitem_fgroup' id='fgroup_id_realname_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>User full name </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_482'><label for='id_realname_op' class='accesshide'>&nbsp;</label><select id='id_realname_op' name='realname_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
</select><label for='id_realname' class='accesshide'>&nbsp;</label><input type='text' id='id_realname' name='realname'></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_lastname_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Surname<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_484'><label for='id_lastname_op' class='accesshide'>&nbsp;</label><select id='id_lastname_op' name='lastname_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
</select><label for='id_lastname' class='accesshide'>&nbsp;</label></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_firstname_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>First name<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_486'><label for='id_firstname_op' class='accesshide'>&nbsp;</label><select id='id_firstname_op' name='firstname_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
</select><label for='id_firstname' class='accesshide'>&nbsp;</label></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_email_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Email address<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_488'><label for='id_email_op' class='accesshide'>&nbsp;</label><select id='id_email_op' name='email_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
</select><label for='id_email' class='accesshide'>&nbsp;</label></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_city_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>City/town<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_490'><label for='id_city_op' class='accesshide'>&nbsp;</label><select id='id_city_op' name='city_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
</select><label for='id_city' class='accesshide'>&nbsp;</label></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_country_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Country<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_492'><label for='id_country_op' class='accesshide'>&nbsp;</label><select id='id_country_op' name='country_op'>
	<option value='0'>is any value</option>
	<option value='1'>is equal to</option>
	<option value='2'>isn't equal to</option>
</select><label for='id_country' class='accesshide'>&nbsp;</label>
	</fieldset></div>
		<div class='fitem advanced fitem_fselect' id='fitem_id_confirmed'><div class='fitemtitle'><label for='id_confirmed'>Confirmed<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div><div class='felement fselect'><select id='id_confirmed' name='confirmed'>
	<option value=''>any value</option>
	<option value='0'>No</option>
	<option value='1'>Yes</option>
</select></div></div>
		<div class='fitem advanced fitem_fselect' id='fitem_id_suspended'><div class='fitemtitle'><label for='id_suspended'>Suspended account<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div><div class='felement fselect'><select id='id_suspended' name='suspended'>
	<option value=''>any value</option>
	<option value='0'>No</option>
	<option value='1'>Yes</option>
</select></div></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_profile_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Profile<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup'><label for='id_profile_fld' class='accesshide'>&nbsp;</label><select id='id_profile_fld' name='profile_fld'>
	<option value='0'>any field</option>
	<option value='1'>userprogram</option>
</select><label for='id_profile_op' class='accesshide'>&nbsp;</label><select id='id_profile_op' name='profile_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
	<option value='6'>isn't defined</option>
	<option value='7'>is defined</option>
</select><label for='id_profile' class='accesshide'>&nbsp;</label><input type='text' id='id_profile' name='profile'></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_courserole_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Course role<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup'><label for='id_courserole_rl' class='accesshide'>&nbsp;</label><select id='id_courserole_rl' name='courserole_rl'>
	<option value='0'>any role</option>
	<option value='5'>Student</option>
	<option value='4'>Non-editing teacher</option>
	<option value='3'>Teacher</option>
	<option value='1'>Manager</option>
</select><label for='id_courserole_ct' class='accesshide'>&nbsp;</label><select id='id_courserole_ct' name='courserole_ct'>
	<option value='0'>any category</option>
	<option value='1'>Miscellaneous</option>
</select><label for='id_courserole' class='accesshide'>&nbsp;</label><input type='text' id='id_courserole' name='courserole'></fieldset></div>
		<div class='fitem advanced fitem_fselect' id='fitem_id_systemrole'><div class='fitemtitle'><label for='id_systemrole'>System role<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div><div class='felement fselect'><select id='id_systemrole' name='systemrole'>
	<option selected='selected' value='0'>any role</option>
	<option value='1'>Manager</option>
	<option value='2'>Course creator</option>
	<option value='5'>Student</option>
</select></div></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_cohort_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Cohort ID<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_494'><label for='id_cohort_op' class='accesshide'>&nbsp;</label><select id='id_cohort_op' name='cohort_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option selected='selected' value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
</select><label for='id_cohort' class='accesshide'>&nbsp;</label><input type='text' id='id_cohort' name='cohort'></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_firstaccess_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>First access<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_496'><span id='yui_3_7_3_2_1367296047967_503'><input type='checkbox' id='id_firstaccess_sck' value='1' name='firstaccess_sck'><label for='id_firstaccess_sck'>is after</label></span><label for='id_firstaccess_sdt_day' class='accesshide'>Day</label><select id='id_firstaccess_sdt_day' name='firstaccess_sdt[day]' disabled='disabled'>
	<option value='1'>1</option>
	<option value='2'>2</option>
	<option value='3'>3</option>
	<option value='4'>4</option>
	<option value='5'>5</option>
	<option value='6'>6</option>
	<option value='7'>7</option>
	<option value='8'>8</option>
	<option value='9'>9</option>
	<option value='10'>10</option>
	<option value='11'>11</option>
	<option value='12'>12</option>
	<option value='13'>13</option>
	<option value='14'>14</option>
	<option value='15'>15</option>
	<option value='16'>16</option>
	<option value='17'>17</option>
	<option value='18'>18</option>
	<option value='19'>19</option>
	<option value='20'>20</option>
	<option value='21'>21</option>
	<option value='22'>22</option>
	<option value='23'>23</option>
	<option value='24'>24</option>
	<option value='25'>25</option>
	<option value='26'>26</option>
	<option value='27'>27</option>
	<option value='28'>28</option>
	<option value='29'>29</option>
	<option selected='selected' value='30'>30</option>
	<option value='31'>31</option>
</select>&nbsp;<label for='id_firstaccess_sdt_month' class='accesshide'>Month</label><select id='id_firstaccess_sdt_month' name='firstaccess_sdt[month]' disabled='disabled'>
	<option value='1'>January</option>
	<option value='2'>February</option>
	<option value='3'>March</option>
	<option selected='selected' value='4'>April</option>
	<option value='5'>May</option>
	<option value='6'>June</option>
	<option value='7'>July</option>
	<option value='8'>August</option>
	<option value='9'>September</option>
	<option value='10'>October</option>
	<option value='11'>November</option>
	<option value='12'>December</option>
</select>&nbsp;<label for='id_firstaccess_sdt_year' class='accesshide'>Year</label><select id='id_firstaccess_sdt_year' name='firstaccess_sdt[year]' disabled='disabled'>
	<option value='1970'>1970</option>
	<option value='1971'>1971</option>
	<option value='1972'>1972</option>
	<option value='1973'>1973</option>
	<option value='1974'>1974</option>
	<option value='1975'>1975</option>
	<option value='1976'>1976</option>
	<option value='1977'>1977</option>
	<option value='1978'>1978</option>
	<option value='1979'>1979</option>
	<option value='1980'>1980</option>
	<option value='1981'>1981</option>
	<option value='1982'>1982</option>
	<option value='1983'>1983</option>
	<option value='1984'>1984</option>
	<option value='1985'>1985</option>
	<option value='1986'>1986</option>
	<option value='1987'>1987</option>
	<option value='1988'>1988</option>
	<option value='1989'>1989</option>
	<option value='1990'>1990</option>
	<option value='1991'>1991</option>
	<option value='1992'>1992</option>
	<option value='1993'>1993</option>
	<option value='1994'>1994</option>
	<option value='1995'>1995</option>
	<option value='1996'>1996</option>
	<option value='1997'>1997</option>
	<option value='1998'>1998</option>
	<option value='1999'>1999</option>
	<option value='2000'>2000</option>
	<option value='2001'>2001</option>
	<option value='2002'>2002</option>
	<option value='2003'>2003</option>
	<option value='2004'>2004</option>
	<option value='2005'>2005</option>
	<option value='2006'>2006</option>
	<option value='2007'>2007</option>
	<option value='2008'>2008</option>
	<option value='2009'>2009</option>
	<option value='2010'>2010</option>
	<option value='2011'>2011</option>
	<option value='2012'>2012</option>
	<option selected='selected' value='2013'>2013</option>
	<option value='2014'>2014</option>
	<option value='2015'>2015</option>
	<option value='2016'>2016</option>
	<option value='2017'>2017</option>
	<option value='2018'>2018</option>
	<option value='2019'>2019</option>
	<option value='2020'>2020</option>
</select><br><span id='yui_3_7_3_2_1367296047967_502'><input type='checkbox' id='id_firstaccess_eck' value='1' name='firstaccess_eck'><label for='id_firstaccess_eck'>is before</label></span><label for='id_firstaccess_edt_day' class='accesshide'>Day</label><select id='id_firstaccess_edt_day' name='firstaccess_edt[day]' disabled='disabled'>
	<option value='1'>1</option>
	<option value='2'>2</option>
	<option value='3'>3</option>
	<option value='4'>4</option>
	<option value='5'>5</option>
	<option value='6'>6</option>
	<option value='7'>7</option>
	<option value='8'>8</option>
	<option value='9'>9</option>
	<option value='10'>10</option>
	<option value='11'>11</option>
	<option value='12'>12</option>
	<option value='13'>13</option>
	<option value='14'>14</option>
	<option value='15'>15</option>
	<option value='16'>16</option>
	<option value='17'>17</option>
	<option value='18'>18</option>
	<option value='19'>19</option>
	<option value='20'>20</option>
	<option value='21'>21</option>
	<option value='22'>22</option>
	<option value='23'>23</option>
	<option value='24'>24</option>
	<option value='25'>25</option>
	<option value='26'>26</option>
	<option value='27'>27</option>
	<option value='28'>28</option>
	<option value='29'>29</option>
	<option selected='selected' value='30'>30</option>
	<option value='31'>31</option>
</select>&nbsp;<label for='id_firstaccess_edt_month' class='accesshide'>Month</label><select id='id_firstaccess_edt_month' name='firstaccess_edt[month]' disabled='disabled'>
	<option value='1'>January</option>
	<option value='2'>February</option>
	<option value='3'>March</option>
	<option selected='selected' value='4'>April</option>
	<option value='5'>May</option>
	<option value='6'>June</option>
	<option value='7'>July</option>
	<option value='8'>August</option>
	<option value='9'>September</option>
	<option value='10'>October</option>
	<option value='11'>November</option>
	<option value='12'>December</option>
</select>&nbsp;<label for='id_firstaccess_edt_year' class='accesshide'>Year</label><select id='id_firstaccess_edt_year' name='firstaccess_edt[year]' disabled='disabled'>
	<option value='1970'>1970</option>
	<option value='1971'>1971</option>
	<option value='1972'>1972</option>
	<option value='1973'>1973</option>
	<option value='1974'>1974</option>
	<option value='1975'>1975</option>
	<option value='1976'>1976</option>
	<option value='1977'>1977</option>
	<option value='1978'>1978</option>
	<option value='1979'>1979</option>
	<option value='1980'>1980</option>
	<option value='1981'>1981</option>
	<option value='1982'>1982</option>
	<option value='1983'>1983</option>
	<option value='1984'>1984</option>
	<option value='1985'>1985</option>
	<option value='1986'>1986</option>
	<option value='1987'>1987</option>
	<option value='1988'>1988</option>
	<option value='1989'>1989</option>
	<option value='1990'>1990</option>
	<option value='1991'>1991</option>
	<option value='1992'>1992</option>
	<option value='1993'>1993</option>
	<option value='1994'>1994</option>
	<option value='1995'>1995</option>
	<option value='1996'>1996</option>
	<option value='1997'>1997</option>
	<option value='1998'>1998</option>
	<option value='1999'>1999</option>
	<option value='2000'>2000</option>
	<option value='2001'>2001</option>
	<option value='2002'>2002</option>
	<option value='2003'>2003</option>
	<option value='2004'>2004</option>
	<option value='2005'>2005</option>
	<option value='2006'>2006</option>
	<option value='2007'>2007</option>
	<option value='2008'>2008</option>
	<option value='2009'>2009</option>
	<option value='2010'>2010</option>
	<option value='2011'>2011</option>
	<option value='2012'>2012</option>
	<option selected='selected' value='2013'>2013</option>
	<option value='2014'>2014</option>
	<option value='2015'>2015</option>
	<option value='2016'>2016</option>
	<option value='2017'>2017</option>
	<option value='2018'>2018</option>
	<option value='2019'>2019</option>
	<option value='2020'>2020</option>
</select></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_lastaccess_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Last access<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_498'><span id='yui_3_7_3_2_1367296047967_500'><input type='checkbox' id='id_lastaccess_sck' value='1' name='lastaccess_sck'><label for='id_lastaccess_sck'>is after</label></span><label for='id_lastaccess_sdt_day' class='accesshide'>Day</label><select id='id_lastaccess_sdt_day' name='lastaccess_sdt[day]' disabled='disabled'>
	<option value='1'>1</option>
	<option value='2'>2</option>
	<option value='3'>3</option>
	<option value='4'>4</option>
	<option value='5'>5</option>
	<option value='6'>6</option>
	<option value='7'>7</option>
	<option value='8'>8</option>
	<option value='9'>9</option>
	<option value='10'>10</option>
	<option value='11'>11</option>
	<option value='12'>12</option>
	<option value='13'>13</option>
	<option value='14'>14</option>
	<option value='15'>15</option>
	<option value='16'>16</option>
	<option value='17'>17</option>
	<option value='18'>18</option>
	<option value='19'>19</option>
	<option value='20'>20</option>
	<option value='21'>21</option>
	<option value='22'>22</option>
	<option value='23'>23</option>
	<option value='24'>24</option>
	<option value='25'>25</option>
	<option value='26'>26</option>
	<option value='27'>27</option>
	<option value='28'>28</option>
	<option value='29'>29</option>
	<option selected='selected' value='30'>30</option>
	<option value='31'>31</option>
</select>&nbsp;<label for='id_lastaccess_sdt_month' class='accesshide'>Month</label><select id='id_lastaccess_sdt_month' name='lastaccess_sdt[month]' disabled='disabled'>
	<option value='1'>January</option>
	<option value='2'>February</option>
	<option value='3'>March</option>
	<option selected='selected' value='4'>April</option>
	<option value='5'>May</option>
	<option value='6'>June</option>
	<option value='7'>July</option>
	<option value='8'>August</option>
	<option value='9'>September</option>
	<option value='10'>October</option>
	<option value='11'>November</option>
	<option value='12'>December</option>
</select>&nbsp;<label for='id_lastaccess_sdt_year' class='accesshide'>Year</label><select id='id_lastaccess_sdt_year' name='lastaccess_sdt[year]' disabled='disabled'>
	<option value='1970'>1970</option>
	<option value='1971'>1971</option>
	<option value='1972'>1972</option>
	<option value='1973'>1973</option>
	<option value='1974'>1974</option>
	<option value='1975'>1975</option>
	<option value='1976'>1976</option>
	<option value='1977'>1977</option>
	<option value='1978'>1978</option>
	<option value='1979'>1979</option>
	<option value='1980'>1980</option>
	<option value='1981'>1981</option>
	<option value='1982'>1982</option>
	<option value='1983'>1983</option>
	<option value='1984'>1984</option>
	<option value='1985'>1985</option>
	<option value='1986'>1986</option>
	<option value='1987'>1987</option>
	<option value='1988'>1988</option>
	<option value='1989'>1989</option>
	<option value='1990'>1990</option>
	<option value='1991'>1991</option>
	<option value='1992'>1992</option>
	<option value='1993'>1993</option>
	<option value='1994'>1994</option>
	<option value='1995'>1995</option>
	<option value='1996'>1996</option>
	<option value='1997'>1997</option>
	<option value='1998'>1998</option>
	<option value='1999'>1999</option>
	<option value='2000'>2000</option>
	<option value='2001'>2001</option>
	<option value='2002'>2002</option>
	<option value='2003'>2003</option>
	<option value='2004'>2004</option>
	<option value='2005'>2005</option>
	<option value='2006'>2006</option>
	<option value='2007'>2007</option>
	<option value='2008'>2008</option>
	<option value='2009'>2009</option>
	<option value='2010'>2010</option>
	<option value='2011'>2011</option>
	<option value='2012'>2012</option>
	<option selected='selected' value='2013'>2013</option>
	<option value='2014'>2014</option>
	<option value='2015'>2015</option>
	<option value='2016'>2016</option>
	<option value='2017'>2017</option>
	<option value='2018'>2018</option>
	<option value='2019'>2019</option>
	<option value='2020'>2020</option>
</select><br><span id='yui_3_7_3_2_1367296047967_501'><input type='checkbox' id='id_lastaccess_eck' value='1' name='lastaccess_eck'><label for='id_lastaccess_eck'>is before</label></span><label for='id_lastaccess_edt_day' class='accesshide'>Day</label><select id='id_lastaccess_edt_day' name='lastaccess_edt[day]' disabled='disabled'>
	<option value='1'>1</option>
	<option value='2'>2</option>
	<option value='3'>3</option>
	<option value='4'>4</option>
	<option value='5'>5</option>
	<option value='6'>6</option>
	<option value='7'>7</option>
	<option value='8'>8</option>
	<option value='9'>9</option>
	<option value='10'>10</option>
	<option value='11'>11</option>
	<option value='12'>12</option>
	<option value='13'>13</option>
	<option value='14'>14</option>
	<option value='15'>15</option>
	<option value='16'>16</option>
	<option value='17'>17</option>
	<option value='18'>18</option>
	<option value='19'>19</option>
	<option value='20'>20</option>
	<option value='21'>21</option>
	<option value='22'>22</option>
	<option value='23'>23</option>
	<option value='24'>24</option>
	<option value='25'>25</option>
	<option value='26'>26</option>
	<option value='27'>27</option>
	<option value='28'>28</option>
	<option value='29'>29</option>
	<option selected='selected' value='30'>30</option>
	<option value='31'>31</option>
</select>&nbsp;<label for='id_lastaccess_edt_month' class='accesshide'>Month</label><select id='id_lastaccess_edt_month' name='lastaccess_edt[month]' disabled='disabled'>
	<option value='1'>January</option>
	<option value='2'>February</option>
	<option value='3'>March</option>
	<option selected='selected' value='4'>April</option>
	<option value='5'>May</option>
	<option value='6'>June</option>
	<option value='7'>July</option>
	<option value='8'>August</option>
	<option value='9'>September</option>
	<option value='10'>October</option>
	<option value='11'>November</option>
	<option value='12'>December</option>
</select>&nbsp;<label for='id_lastaccess_edt_year' class='accesshide'>Year</label><select id='id_lastaccess_edt_year' name='lastaccess_edt[year]' disabled='disabled'>
	<option value='1970'>1970</option>
	<option value='1971'>1971</option>
	<option value='1972'>1972</option>
	<option value='1973'>1973</option>
	<option value='1974'>1974</option>
	<option value='1975'>1975</option>
	<option value='1976'>1976</option>
	<option value='1977'>1977</option>
	<option value='1978'>1978</option>
	<option value='1979'>1979</option>
	<option value='1980'>1980</option>
	<option value='1981'>1981</option>
	<option value='1982'>1982</option>
	<option value='1983'>1983</option>
	<option value='1984'>1984</option>
	<option value='1985'>1985</option>
	<option value='1986'>1986</option>
	<option value='1987'>1987</option>
	<option value='1988'>1988</option>
	<option value='1989'>1989</option>
	<option value='1990'>1990</option>
	<option value='1991'>1991</option>
	<option value='1992'>1992</option>
	<option value='1993'>1993</option>
	<option value='1994'>1994</option>
	<option value='1995'>1995</option>
	<option value='1996'>1996</option>
	<option value='1997'>1997</option>
	<option value='1998'>1998</option>
	<option value='1999'>1999</option>
	<option value='2000'>2000</option>
	<option value='2001'>2001</option>
	<option value='2002'>2002</option>
	<option value='2003'>2003</option>
	<option value='2004'>2004</option>
	<option value='2005'>2005</option>
	<option value='2006'>2006</option>
	<option value='2007'>2007</option>
	<option value='2008'>2008</option>
	<option value='2009'>2009</option>
	<option value='2010'>2010</option>
	<option value='2011'>2011</option>
	<option value='2012'>2012</option>
	<option selected='selected' value='2013'>2013</option>
	<option value='2014'>2014</option>
	<option value='2015'>2015</option>
	<option value='2016'>2016</option>
	<option value='2017'>2017</option>
	<option value='2018'>2018</option>
	<option value='2019'>2019</option>
	<option value='2020'>2020</option>
</select></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_neveraccessed_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Never accessed<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup'><span><input type='checkbox' id='id_neveraccessed' value='1' name='neveraccessed'></span></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_timemodified_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Last modified<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_504'><span id='yui_3_7_3_2_1367296047967_506'><input type='checkbox' id='id_timemodified_sck' value='1' name='timemodified_sck'><label for='id_timemodified_sck'>is after</label></span><label for='id_timemodified_sdt_day' class='accesshide'>Day</label><select id='id_timemodified_sdt_day' name='timemodified_sdt[day]' disabled='disabled'>
	<option value='1'>1</option>
	<option value='2'>2</option>
	<option value='3'>3</option>
	<option value='4'>4</option>
	<option value='5'>5</option>
	<option value='6'>6</option>
	<option value='7'>7</option>
	<option value='8'>8</option>
	<option value='9'>9</option>
	<option value='10'>10</option>
	<option value='11'>11</option>
	<option value='12'>12</option>
	<option value='13'>13</option>
	<option value='14'>14</option>
	<option value='15'>15</option>
	<option value='16'>16</option>
	<option value='17'>17</option>
	<option value='18'>18</option>
	<option value='19'>19</option>
	<option value='20'>20</option>
	<option value='21'>21</option>
	<option value='22'>22</option>
	<option value='23'>23</option>
	<option value='24'>24</option>
	<option value='25'>25</option>
	<option value='26'>26</option>
	<option value='27'>27</option>
	<option value='28'>28</option>
	<option value='29'>29</option>
	<option selected='selected' value='30'>30</option>
	<option value='31'>31</option>
</select>&nbsp;<label for='id_timemodified_sdt_month' class='accesshide'>Month</label><select id='id_timemodified_sdt_month' name='timemodified_sdt[month]' disabled='disabled'>
	<option value='1'>January</option>
	<option value='2'>February</option>
	<option value='3'>March</option>
	<option selected='selected' value='4'>April</option>
	<option value='5'>May</option>
	<option value='6'>June</option>
	<option value='7'>July</option>
	<option value='8'>August</option>
	<option value='9'>September</option>
	<option value='10'>October</option>
	<option value='11'>November</option>
	<option value='12'>December</option>
</select>&nbsp;<label for='id_timemodified_sdt_year' class='accesshide'>Year</label><select id='id_timemodified_sdt_year' name='timemodified_sdt[year]' disabled='disabled'>
	<option value='1970'>1970</option>
	<option value='1971'>1971</option>
	<option value='1972'>1972</option>
	<option value='1973'>1973</option>
	<option value='1974'>1974</option>
	<option value='1975'>1975</option>
	<option value='1976'>1976</option>
	<option value='1977'>1977</option>
	<option value='1978'>1978</option>
	<option value='1979'>1979</option>
	<option value='1980'>1980</option>
	<option value='1981'>1981</option>
	<option value='1982'>1982</option>
	<option value='1983'>1983</option>
	<option value='1984'>1984</option>
	<option value='1985'>1985</option>
	<option value='1986'>1986</option>
	<option value='1987'>1987</option>
	<option value='1988'>1988</option>
	<option value='1989'>1989</option>
	<option value='1990'>1990</option>
	<option value='1991'>1991</option>
	<option value='1992'>1992</option>
	<option value='1993'>1993</option>
	<option value='1994'>1994</option>
	<option value='1995'>1995</option>
	<option value='1996'>1996</option>
	<option value='1997'>1997</option>
	<option value='1998'>1998</option>
	<option value='1999'>1999</option>
	<option value='2000'>2000</option>
	<option value='2001'>2001</option>
	<option value='2002'>2002</option>
	<option value='2003'>2003</option>
	<option value='2004'>2004</option>
	<option value='2005'>2005</option>
	<option value='2006'>2006</option>
	<option value='2007'>2007</option>
	<option value='2008'>2008</option>
	<option value='2009'>2009</option>
	<option value='2010'>2010</option>
	<option value='2011'>2011</option>
	<option value='2012'>2012</option>
	<option selected='selected' value='2013'>2013</option>
	<option value='2014'>2014</option>
	<option value='2015'>2015</option>
	<option value='2016'>2016</option>
	<option value='2017'>2017</option>
	<option value='2018'>2018</option>
	<option value='2019'>2019</option>
	<option value='2020'>2020</option>
</select><br><span id='yui_3_7_3_2_1367296047967_507'><input type='checkbox' id='id_timemodified_eck' value='1' name='timemodified_eck'><label for='id_timemodified_eck'>is before</label></span><label for='id_timemodified_edt_day' class='accesshide'>Day</label><select id='id_timemodified_edt_day' name='timemodified_edt[day]' disabled='disabled'>
	<option value='1'>1</option>
	<option value='2'>2</option>
	<option value='3'>3</option>
	<option value='4'>4</option>
	<option value='5'>5</option>
	<option value='6'>6</option>
	<option value='7'>7</option>
	<option value='8'>8</option>
	<option value='9'>9</option>
	<option value='10'>10</option>
	<option value='11'>11</option>
	<option value='12'>12</option>
	<option value='13'>13</option>
	<option value='14'>14</option>
	<option value='15'>15</option>
	<option value='16'>16</option>
	<option value='17'>17</option>
	<option value='18'>18</option>
	<option value='19'>19</option>
	<option value='20'>20</option>
	<option value='21'>21</option>
	<option value='22'>22</option>
	<option value='23'>23</option>
	<option value='24'>24</option>
	<option value='25'>25</option>
	<option value='26'>26</option>
	<option value='27'>27</option>
	<option value='28'>28</option>
	<option value='29'>29</option>
	<option selected='selected' value='30'>30</option>
	<option value='31'>31</option>
</select>&nbsp;<label for='id_timemodified_edt_month' class='accesshide'>Month</label><select id='id_timemodified_edt_month' name='timemodified_edt[month]' disabled='disabled'>
	<option value='1'>January</option>
	<option value='2'>February</option>
	<option value='3'>March</option>
	<option selected='selected' value='4'>April</option>
	<option value='5'>May</option>
	<option value='6'>June</option>
	<option value='7'>July</option>
	<option value='8'>August</option>
	<option value='9'>September</option>
	<option value='10'>October</option>
	<option value='11'>November</option>
	<option value='12'>December</option>
</select>&nbsp;<label for='id_timemodified_edt_year' class='accesshide'>Year</label><select id='id_timemodified_edt_year' name='timemodified_edt[year]' disabled='disabled'>
	<option value='1970'>1970</option>
	<option value='1971'>1971</option>
	<option value='1972'>1972</option>
	<option value='1973'>1973</option>
	<option value='1974'>1974</option>
	<option value='1975'>1975</option>
	<option value='1976'>1976</option>
	<option value='1977'>1977</option>
	<option value='1978'>1978</option>
	<option value='1979'>1979</option>
	<option value='1980'>1980</option>
	<option value='1981'>1981</option>
	<option value='1982'>1982</option>
	<option value='1983'>1983</option>
	<option value='1984'>1984</option>
	<option value='1985'>1985</option>
	<option value='1986'>1986</option>
	<option value='1987'>1987</option>
	<option value='1988'>1988</option>
	<option value='1989'>1989</option>
	<option value='1990'>1990</option>
	<option value='1991'>1991</option>
	<option value='1992'>1992</option>
	<option value='1993'>1993</option>
	<option value='1994'>1994</option>
	<option value='1995'>1995</option>
	<option value='1996'>1996</option>
	<option value='1997'>1997</option>
	<option value='1998'>1998</option>
	<option value='1999'>1999</option>
	<option value='2000'>2000</option>
	<option value='2001'>2001</option>
	<option value='2002'>2002</option>
	<option value='2003'>2003</option>
	<option value='2004'>2004</option>
	<option value='2005'>2005</option>
	<option value='2006'>2006</option>
	<option value='2007'>2007</option>
	<option value='2008'>2008</option>
	<option value='2009'>2009</option>
	<option value='2010'>2010</option>
	<option value='2011'>2011</option>
	<option value='2012'>2012</option>
	<option selected='selected' value='2013'>2013</option>
	<option value='2014'>2014</option>
	<option value='2015'>2015</option>
	<option value='2016'>2016</option>
	<option value='2017'>2017</option>
	<option value='2018'>2018</option>
	<option value='2019'>2019</option>
	<option value='2020'>2020</option>
</select></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_nevermodified_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Never modified<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup'><span><input type='checkbox' id='id_nevermodified' value='1' name='nevermodified'></span></fieldset></div>
		<div class='fitem advanced fitem_fgroup' id='fgroup_id_username_grp'><div class='fitemtitle'><div class='fgrouplabel'><label>Username<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div></div><fieldset class='felement fgroup' id='yui_3_7_3_2_1367296047967_508'><label for='id_username_op' class='accesshide'>&nbsp;</label><select id='id_username_op' name='username_op'>
	<option value='0'>contains</option>
	<option value='1'>doesn't contain</option>
	<option value='2'>is equal to</option>
	<option value='3'>starts with</option>
	<option value='4'>ends with</option>
	<option value='5'>is empty</option>
</select><label for='id_username' class='accesshide'>&nbsp;</label></fieldset></div>
		<div class='fitem advanced fitem_fselect' id='fitem_id_auth'><div class='fitemtitle'><label for='id_auth'>Authentication<img src='http://dev.paradisosolutions.com/astm/moodle/theme/image.php?theme=afterburner&amp;component=core&amp;image=adv' alt='Advanced element' title='Advanced element' class='adv'> </label></div><div class='felement fselect'><select id='id_auth' name='auth'>
	<option value=''>any value</option>
	<option value='cas'>CAS server (SSO)</option>
	<option value='db'>External database</option>
	<option value='email'>Email-based self-registration</option>
	<option value='fc'>FirstClass server</option>
	<option value='imap'>IMAP server</option>
	<option value='ldap'>LDAP server</option>
	<option value='manual'>Manual accounts</option>
	<option value='mnet'>MNet authentication</option>
	<option value='nntp'>NNTP server</option>
	<option value='nologin'>No login</option>
	<option value='none'>No authentication</option>
	<option value='pam'>PAM (Pluggable Authentication Modules)</option>
	<option value='pop3'>POP3 server</option>
	<option value='radius'>RADIUS server</option>
	<option value='shibboleth'>Shibboleth</option>
	<option value='webservice'>Web services authentication</option>
</select></div></div>
		<div class='fitem fitem_actionbuttons fitem_fsubmit' id='fitem_id_addfilter'><div class='felement fsubmit'><input type='submit' id='id_addfilter' value='Add filter' name='addfilter'></div></div>
		</div>
		</div>";
		echo html_writer::end_tag("form");
		echo html_writer::end_tag("div");
		echo "<script src='http://code.jquery.com/jquery-1.9.1.min.js'></script>";
		echo "<script src='".$CFG->wwwroot ."/admin/jquery.placeholder.js'></script>";
		echo "<script>
					$(document).ready(function() {
						var value = $('#mform1 input[name=sesskey]').val();
						$('#sesskey').val(value);
						if($('#sesskey').val() == ''){
							var value2 = $('#mform2 input[name=sesskey]').val();
							$('#sesskey').val(value2);
						}
						$('#mform1').hide();
						$('#mform2').hide();
						$('#form_astm').insertBefore('.generaltable');
						$('#region-pre').insertAfter('#region-main');
					});
					
					$(function() {
						$('input').placeholder();
					});
					
					
			</script>";
	

    if (has_capability('moodle/user:create', $sitecontext)) {
        //echo $OUTPUT->heading('<a href="'.$securewwwroot.'/user/editadvanced.php?id=-1">'.get_string('addnewuser').'</a>');
    }
    if (!empty($table)) {
		
		echo html_writer::table($table);
		
		
        echo $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);
        if (has_capability('moodle/user:create', $sitecontext)) {
            //echo $OUTPUT->heading('<a href="'.$securewwwroot.'/user/editadvanced.php?id=-1">'.get_string('addnewuser').'</a>');
        }
    }

    echo $OUTPUT->footer();



