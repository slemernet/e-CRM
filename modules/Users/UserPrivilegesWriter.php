<?php
/*************************************************************************************************
 * Copyright 2012 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
 * Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
 * file except in compliance with the License. You can redistribute it and/or modify it
 * under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
 * granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
 * applicable law or agreed to in writing, software distributed under the License is
 * distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
 * either express or implied. See the License for the specific language governing
 * permissions and limitations under the License. You may obtain a copy of the License
 * at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
 *************************************************************************************************/

class UserPrivilegesWriter {

	const WRITE_TO = 'file'; // file | db

	public static function setUserPrivileges($userId) {
		if (self::WRITE_TO == 'file') {
			self::createUserPrivilegesFile($userId);
		} elseif (self::WRITE_TO == 'db') {
			self::createUserPrivileges($userId);
		}
	}

	private static function setSharingPrivileges($userId) {
		if (self::WRITE_TO == 'file') {
			self::createSharingPrivilegesFile($userId);
		} elseif (self::WRITE_TO == 'db') {
			self::createSharingPrivileges($userId);
		}
	}

	/**
	 * Method to write user privileges in a file
	 *
	 * @param  int $userid
	 */
	private function createUserPrivilegesFile($userId) {
		require_once 'modules/Users/CreateUserPrivilegeFile.php';
		createUserPrivilegesfile($userId);
	}

	/**
	 * Method to write sharing privileges in a file
	 *
	 * @param  int $userid
	 */
	private function createSharingPrivilegesFile($userId) {
		require_once 'modules/Users/CreateUserPrivilegeFile.php';
		createUserSharingPrivilegesfile($userId);
	}

	/**
	 * Method to write user privileges in database
	 *
	 * @param  int $userId [description]
	 */
	private function createUserPrivileges($userId) {
		global $adb;

		$privs = array();
		$userFocus = new Users();
		$userFocus->retrieve_entity_info($userId, "Users");
		$userInfo = array();
		$userFocus->column_fields["id"] = '';
		$userFocus->id = $userId;
		foreach ($userFocus->column_fields as $field => $value) {
			if (isset($userFocus->$field)) {
				$userInfo[$field] = $userFocus->$field;
			}
		}
		$privs["user_info"] = $userFocus;
		$privs["is_admin"] = ($userFocus->is_admin == 'on') ? true : false;

		$userRole = fetchUserRole($userId);
		$userRoleInfo = getRoleInformation($userRole);
		$userRoleParent = $userRoleInfo[$userRole][1];
		$userGroupFocus = new GetUserGroups();
		$userGroupFocus->getAllUserGroups($userId);

		if (!$privs["is_admin"]) {
			$privs["current_user_roles"] = $userRole;
			$privs["current_user_parent_role_seq"] = $userRoleParent;
			$privs["current_user_profiles"] = getUserProfile($userId);
			$privs["profileGlobalPermission"] = getCombinedUserGlobalPermissions($userId);
			$privs["profileTabsPermission"] = getCombinedUserTabsPermissions($userId);
			$privs["profileActionPermission"] = getCombinedUserActionPermissions($userId);
			$privs["current_user_groups"] = $userGroupFocus->user_groups;
			$privs["subordinate_roles"] = getRoleSubordinates($userRole);
			$privs["parent_roles"] = getParentRole($userRole);
			$privs["subordinate_roles_users"] = getSubordinateRoleAndUsers($userRole);
		}
		$encodedPrivs = json_encode($privs);

		$adb->pquery(
			"INSERT ON user_privileges(userid, user_data)
			VALUES (?, ?)
			ON DUPLICATE KEY UPDATE
			user_data = ?",
			array($userId, $encodedPrivs, $encodedPrivs)
		);
	}

	/**
	 * Method to write sharing privileges in database
	 *
	 * @param  int $userId [description]
	 */
	private function createSharingPrivileges($userId) {

		$sharingPrivs = array();
		if (empty($userId)) {
			return false;
		}

		$userFocus = new Users();
		$userFocus->retrieve_entity_info($userId, "Users");
		if ($userFocus->is_admin == 'on') {
			return;
		}

		$userPrivs = $userFocus->getPrivileges();

		$sharingPrivs["defaultOrgSharingPermission"] = getAllDefaultSharingAction();
		$sharingPrivs["related_module_share"] = self::relatedModuleSharing();

		self::LeadsPrivileges($userFocus, $sharingPrivs);
		self::AccountsPrivileges($userFocus, $sharingPrivs);
		self::PotentialsPrivileges($userFocus, $sharingPrivs);
		self::QuotesPrivileges($userFocus, $sharingPrivs);
		self::SalesOrderPrivileges($userFocus, $sharingPrivs);

		self::ModulePrivileges('HelpDesk', $user, $sharingPrivs);
		self::ModulePrivileges('Emails', $user, $sharingPrivs);
		self::ModulePrivileges('Campaigns', $user, $sharingPrivs);
		self::ModulePrivileges('PurchaseOrder', $user, $sharingPrivs);
		self::ModulePrivileges('Invoice', $user, $sharingPrivs);

		self::Depricated_populateSharingTmpTables($userId, $sharingPrivs);
	}

	/**
	 * Constructing the Related Module Sharing Array
	 *
	 * @return void
	 */
	private function relatedModuleSharing() {
		global $adb;

		$relModSharArr = array();
		$query = "SELECT * from vtiger_datashare_relatedmodules";
		$result = $adb->query($query);
		while ($row = $adb->fetchByAssoc($result)) {
			$parTabId = $row['tabid'];
			$relTabId = $row['relatedto_tabid'];
			if (isset($relModSharArr[$relTabId]) and is_array($relModSharArr[$relTabId])) {
				$temArr = $relModSharArr[$relTabId];
				$temArr[] = $parTabId;
			} else {
				$temArr = array();
				$temArr[] = $parTabId;
			}
			$relModSharArr[$relTabId] = $temArr;
		}

		return $relModSharArr;
	}

	/**
	 * Constructing Lead Sharing Rules
	 *
	 * @param Users $user
	 * @param Array $sharingPrivs
	 *
	 * @return void
	 */
	private function LeadsPrivileges(Users $user, &$sharingPrivs) {

		$lead_share_per_array = self::ModulePrivileges('Leads', $user, $sharingPrivs);

		//Constructing the Lead Email Related Module Sharing Array
		self::constructRelatedSharing(
			"Leads",
			"Emails",
			$lead_share_per_array,
			$sharingPrivs
		);
	}

	/**
	 * Constructing Accounts Sharing Rules
	 *
	 * @param Users $user
	 * @param Array $sharingPrivs
	 *
	 * @return void
	 */
	private function AccountsPrivileges(Users $user, &$sharingPrivs) {

		//Constructing Account Sharing Rules
		$account_share_per_array = self::ModulePrivileges('Accounts', $user, $sharingPrivs);

		self::constructModuleSharing(
			"Accounts",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing Contact Sharing Rules
		self::constructModuleSharing(
			"Contacts",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing the Account Potential Related Module Sharing Array
		self::constructRelatedSharing(
			"Accounts",
			"Potentials",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing the Account Ticket Related Module Sharing Array
		self::constructRelatedSharing(
			"Accounts",
			"HelpDesk",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing the Account Email Related Module Sharing Array
		self::constructRelatedSharing(
			"Accounts",
			"Emails",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing the Account Quote Related Module Sharing Array
		self::constructRelatedSharing(
			"Accounts",
			"Quotes",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing the Account SalesOrder Related Module Sharing Array
		self::constructRelatedSharing(
			"Accounts",
			"SalesOrder",
			$account_share_per_array,
			$sharingPrivs
		);

		//Constructing the Account Invoice Related Module Sharing Array
		self::constructRelatedSharing(
			"Accounts",
			"Invoice",
			$account_share_per_array,
			$sharingPrivs
		);
	}

	/**
	 * Constructing Potentials Sharing Rules
	 *
	 * @param Users $user
	 * @param Array $sharingPrivs
	 *
	 * @return void
	 */
	private function PotentialsPrivileges(Users $user, &$sharingPrivs) {

		$pot_share_per_array = self::ModulePrivileges('Potentials', $user, $sharingPrivs);

		self::constructModuleSharing(
			"Potentials",
			$pot_share_per_array,
			$sharingPrivs
		);

		//Constructing the Potential Quotes Related Module Sharing Array
		self::constructRelatedSharing(
			"Potentials",
			"Quotes",
			$pot_share_per_array,
			$sharingPrivs
		);

		//Constructing the Potential SalesOrder Related Module Sharing Array
		self::constructRelatedSharing(
			"Potentials",
			"SalesOrder",
			$pot_share_per_array,
			$sharingPrivs
		);
	}

	/**
	 * Constructing Quotes Sharing Rules
	 *
	 * @param Users $user
	 * @param Array $sharingPrivs
	 *
	 * @return void
	 */
	private function QuotesPrivileges(Users $user, &$sharingPrivs) {

		$quotes_share_per_array = self::ModulePrivileges('Quotes', $user, $sharingPrivs);

		//Constructing the Lead Email Related Module Sharing Array
		self::constructRelatedSharing(
			"Quotes",
			"SalesOrder",
			$quotes_share_per_array,
			$sharingPrivs
		);
	}

	/**
	 * Constructing SalesOrder Sharing Rules
	 *
	 * @param Users $user
	 * @param Array $sharingPrivs
	 *
	 * @return void
	 */
	private function SalesOrderPrivileges(Users $user, &$sharingPrivs) {

		$share_per_array = self::ModulePrivileges('SalesOrder', $user, $sharingPrivs);

		//Constructing the Lead Email Related Module Sharing Array
		self::constructRelatedSharing(
			"SalesOrder",
			"Invoice",
			$share_per_array,
			$sharingPrivs
		);
	}

	/**
	 * Construct General Module Sharing Rules
	 *
	 * @param String	$module
	 * @param Users		$user
	 * @param Array		$sharingPrivs
	 *
	 * @return Array 	$userModSharing
	 */
	private function ModulePrivileges($module, Users $user, &$sharingPrivs) {
		$userPrivs = $user->getPrivileges();

		$userModSharing = getUserModuleSharingObjects(
			$module,
			$user->id,
			$sharingPrivs["defaultOrgSharingPermission"],
			$userPrivs->getRoles(),
			$userPrivs->getParentRoles(),
			$userPrivs->getGroups()
		);

		self::constructModuleSharing(
			$module,
			$userModSharing,
			$sharingPrivs
		);

		return $userModSharing;
	}

	/**
	 * Method to generate a given module sharing privileges
	 *
	 * @param String 	$module
	 * @param Array 	$sharePerm
	 * @param Array 	$sharingPrivs
	 *
	 * @return void
	 */
	private function constructModuleSharing($module, $sharePerm, &$sharingPrivs) {

		$readPermission = $sharePerm['read'];
		$writePermission = $sharePerm['write'];

		$sharingPrivs["{$module}_share_read_permission"] = array(
			'ROLE' => $readPermission['ROLE'],
			'GROUP' => $readPermission['GROUP']
		);

		$sharingPrivs["{$module}_share_write_permission"] = array(
			'ROLE'=> $writePermission['ROLE'],
			'GROUP'=> $writePermission['GROUP']
		);
	}

	/**
	 * Method to generate related modules sharing privileges
	 *
	 * @param String 	$module
	 * @param String 	$relModule
	 * @param Array		$modSharing
	 * @param Array		$sharingPrivs
	 *
	 * @return void
	 */
	private function constructRelatedSharing($module, $relModule, $modSharing, &$sharingPrivs) {

		$relatedModSharing = getRelatedModuleSharingArray(
			$module,
			$relModule,
			$modSharing["sharingrules"],
			$modSharing["read"],
			$modSharing["write"],
			$sharingPrivs["defaultOrgSharingPermission"]
		);

		$readPermission = $relatedModSharing['read'];
		$writePermission = $relatedModSharing['write'];

		$sharingPrivs["{$module}_{$relModule}_share_read_permission"] = array(
			'ROLE' => $readPermission['ROLE'],
			'GROUP'=> $readPermission['GROUP']
		);

		$sharingPrivs["{$module}_{$relModule}_share_write_permission"] = array(
			'ROLE' => $writePermission['ROLE'],
			'GROUP' => $writePermission['GROUP']
		);
	}

	private function clearTmpSharingTables($userId) {
		global $adb;

		$table_arr = array(
			'vtiger_tmp_read_user_sharing_per',
			'vtiger_tmp_write_user_sharing_per',
			'vtiger_tmp_read_group_sharing_per',
			'vtiger_tmp_write_group_sharing_per',
			'vtiger_tmp_read_user_rel_sharing_per',
			'vtiger_tmp_write_user_rel_sharing_per',
			'vtiger_tmp_read_group_rel_sharing_per',
			'vtiger_tmp_write_group_rel_sharing_per'
		);
		foreach ($table_arr as $tabname) {
			$query = "delete from " . $tabname . " where userid=?";
			$adb->pquery($query, array($userId));
		}
	}

	/**
	 * Depricated function from CreateUserPrivilegeFile.php
	 */
	private function Depricated_populateSharingTmpTables($userId, $sharingPrivs) {
		self::clearTmpSharingTables($userId);

		// Look up for modules for which sharing access is enabled.
		$sharingArray = array('Emails');
		$otherModules = getSharingModuleList();
		$sharingArray = array_merge($sharingArray, $otherModules);

		foreach ($sharingArray as $module) {
			$module_sharing_read_permvar  = $module.'_share_read_permission';
			$module_sharing_write_permvar = $module.'_share_write_permission';
			Depricated_populateSharingPrivileges(
				'USER',
				$userId,
				$module,
				'read',
				$sharingPrivs[$module_sharing_read_permvar],
				$sharingPrivs
			);
			Depricated_populateSharingPrivileges(
				'USER',
				$userId,
				$module,
				'write',
				$sharingPrivs[$module_sharing_write_permvar],
				$sharingPrivs
			);
			Depricated_populateSharingPrivileges(
				'GROUP',
				$userId,
				$module,
				'read',
				$sharingPrivs[$module_sharing_read_permvar],
				$sharingPrivs
			);
			Depricated_populateSharingPrivileges(
				'GROUP',
				$userId,
				$module,
				'write',
				$sharingPrivs[$module_sharing_write_permvar],
				$sharingPrivs
			);
		}

		foreach ($related_module_share as $rel_tab_id => $tabid_arr) {
			$rel_tab_name=getTabname($rel_tab_id);
			foreach ($tabid_arr as $taid) {
				$tab_name=getTabname($taid);
				$relmodule_sharing_read_permvar  = $tab_name.'_'.$rel_tab_name.'_share_read_permission';
				$relmodule_sharing_write_permvar = $tab_name.'_'.$rel_tab_name.'_share_write_permission';
				Depricated_populateRelatedSharingPrivileges(
					'USER',
					$userid,
					$tab_name,
					$rel_tab_name,
					'read',
					$sharingPrivs[$relmodule_sharing_read_permvar],
					$sharingPrivs
				);
				Depricated_populateRelatedSharingPrivileges(
					'USER',
					$userid,
					$tab_name,
					$rel_tab_name,
					'write',
					$sharingPrivs[$relmodule_sharing_write_permvar],
					$sharingPrivs
				);
				Depricated_populateRelatedSharingPrivileges(
					'GROUP',
					$userid,
					$tab_name,
					$rel_tab_name,
					'read',
					$sharingPrivs[$relmodule_sharing_read_permvar],
					$sharingPrivs
				);
				Depricated_populateRelatedSharingPrivileges(
					'GROUP',
					$userid,
					$tab_name,
					$rel_tab_name,
					'write',
					$sharingPrivs[$relmodule_sharing_write_permvar],
					$sharingPrivs
				);
			}
		}
	}

	/**
	 * Depricated function from CreateUserPrivilegeFile.php
	 */
	private function Depricated_populateSharingPrivileges(
		$enttype,
		$userid,
		$module,
		$pertype,
		$var_name_arr = false,
		$sharingPrivs
	) {
		global $adb;
		$tabid=getTabid($module);

		if ($enttype=='USER') {
			if ($pertype =='read') {
				$table_name='vtiger_tmp_read_user_sharing_per';
				$var_name=$module.'_share_read_permission';
			} elseif ($pertype == 'write') {
				$table_name='vtiger_tmp_write_user_sharing_per';
				$var_name=$module.'_share_write_permission';
			}

			// Lookup for the variable if not set through function argument
			if (!$var_name_arr) {
				$var_name_arr=$sharingPrivs[$var_name];
			}
			$user_arr=array();
			if (sizeof($var_name_arr['ROLE']) > 0) {
				foreach ($var_name_arr['ROLE'] as $roleid => $roleusers) {
					foreach ($roleusers as $user_id) {
						if (! in_array($user_id, $user_arr)) {
							$query="insert into ".$table_name." values(?,?,?)";
							$adb->pquery($query, array($userid, $tabid, $user_id));
							$user_arr[]=$user_id;
						}
					}
				}
			}
			if (sizeof($var_name_arr['GROUP']) > 0) {
				foreach ($var_name_arr['GROUP'] as $grpid => $grpusers) {
					foreach ($grpusers as $user_id) {
						if (! in_array($user_id, $user_arr)) {
							$query="insert into ".$table_name." values(?,?,?)";
							$adb->pquery($query, array($userid, $tabid, $user_id));
							$user_arr[]=$user_id;
						}
					}
				}
			}
		} elseif ($enttype=='GROUP') {
			if ($pertype =='read') {
				$table_name='vtiger_tmp_read_group_sharing_per';
				$var_name=$module.'_share_read_permission';
			} elseif ($pertype == 'write') {
				$table_name='vtiger_tmp_write_group_sharing_per';
				$var_name=$module.'_share_write_permission';
			}

			// Lookup for the variable if not set through function argument
			if (!$var_name_arr) {
				$var_name_arr = $$var_name;
			}
			$grp_arr = array();
			if (sizeof($var_name_arr['GROUP']) > 0) {
				foreach ($var_name_arr['GROUP'] as $grpid => $grpusers) {
					if (!in_array($grpid, $grp_arr)) {
						$query="insert into ".$table_name." values(?,?,?)";
						$adb->pquery($query, array($userid, $tabid, $grpid));
						$grp_arr[]=$grpid;
					}
				}
			}
		}
	}

	/**
	 * Depricated function from CreateUserPrivilegeFile.php
	 */
	private function Depricated_populateRelatedSharingPrivileges(
		$enttype,
		$userid,
		$module,
		$relmodule,
		$pertype,
		$var_name_arr = false,
		$sharingPrivs
	) {
		global $adb;
		$tabid=getTabid($module);
		$reltabid=getTabid($relmodule);

		if ($enttype=='USER') {
			if ($pertype =='read') {
				$table_name='vtiger_tmp_read_user_rel_sharing_per';
				$var_name=$module.'_'.$relmodule.'_share_read_permission';
			} elseif ($pertype == 'write') {
				$table_name='vtiger_tmp_write_user_rel_sharing_per';
				$var_name=$module.'_'.$relmodule.'_share_write_permission';
			}

			// Lookup for the variable if not set through function argument
			if (!$var_name_arr) {
				$var_name_arr=$$var_name;
			}
			$user_arr = array();

			if (sizeof($var_name_arr['ROLE']) > 0) {
				foreach ($var_name_arr['ROLE'] as $roleid => $roleusers) {
					foreach ($roleusers as $user_id) {
						if (!in_array($user_id, $user_arr)) {
							$query="insert into ".$table_name." values(?,?,?,?)";
							$adb->pquery($query, array($userid, $tabid, $reltabid, $user_id));
							$user_arr[]=$user_id;
						}
					}
				}
			}

			if (sizeof($var_name_arr['GROUP']) > 0) {
				foreach ($var_name_arr['GROUP'] as $grpid => $grpusers) {
					foreach ($grpusers as $user_id) {
						if (!in_array($user_id, $user_arr)) {
							$query="insert into ".$table_name." values(?,?,?,?)";
							$adb->pquery($query, array($userid, $tabid, $reltabid, $user_id));
							$user_arr[]=$user_id;
						}
					}
				}
			}
		} elseif ($enttype=='GROUP') {
			if ($pertype =='read') {
				$table_name='vtiger_tmp_read_group_rel_sharing_per';
				$var_name=$module.'_'.$relmodule.'_share_read_permission';
			} elseif ($pertype == 'write') {
				$table_name='vtiger_tmp_write_group_rel_sharing_per';
				$var_name=$module.'_'.$relmodule.'_share_write_permission';
			}

			if (!$var_name_arr) {
				$var_name_arr = $sharingPrivs[$var_name];
			}
			$grp_arr=array();

			if (sizeof($var_name_arr['GROUP']) > 0) {
				foreach ($var_name_arr['GROUP'] as $grpid => $grpusers) {
					if (!in_array($grpid, $grp_arr)) {
						$query="insert into ".$table_name." values(?,?,?,?)";
						$adb->pquery($query, array($userid, $tabid, $reltabid, $grpid));
						$grp_arr[]=$grpid;
					}
				}
			}
		}
	}
}