<?php
/**
 * Security section of the CMS
 * @package cms
 * @subpackage security
 */
class SecurityAdmin extends LeftAndMain implements PermissionProvider {

	static $url_segment = 'security';
	
	static $url_rule = '/$Action/$ID/$OtherID';
	
	static $menu_title = 'Users';
	
	static $tree_class = 'Group';
	
	static $subitem_class = 'Member';
	
	static $allowed_actions = array(
		'EditForm',
		'MemberImportForm',
		'memberimport',
		'GroupImportForm',
		'groupimport',
		'RootForm'
	);

	/**
	 * @var Array
	 */
	static $hidden_permissions = array();

	public function init() {
		parent::init();
		Requirements::javascript(SAPPHIRE_ADMIN_DIR . '/javascript/SecurityAdmin.js');
	}
	
	public function getEditForm($id = null, $fields = null) {
		// TODO Duplicate record fetching (see parent implementation)
		if(!$id) $id = $this->currentPageID();
		$form = parent::getEditForm($id);
		
		// TODO Duplicate record fetching (see parent implementation)
		$record = $this->getRecord($id);
		if($record && !$record->canView()) return Security::permissionFailure($this);
		
		if($id && is_numeric($id)) {
			$form = parent::getEditForm($id);
			if(!$form) return false;
		
			$fields = $form->Fields();
			if($fields->hasTabSet() && $record->canEdit()) {
				$fields->findOrMakeTab('Root.Import',_t('Group.IMPORTTABTITLE', 'Import'));
				$fields->addFieldToTab('Root.Import', 
					new LiteralField(
						'MemberImportFormIframe', 
						sprintf(
							'<iframe src="%s" id="MemberImportFormIframe" width="100%%" height="400px" border="0"></iframe>',
							$this->Link('memberimport')
						)
					)
				);
		
				// Filter permissions
				$permissionField = $form->Fields()->dataFieldByName('Permissions');
				if($permissionField) $permissionField->setHiddenPermissions(self::$hidden_permissions);
			}	
			
			$this->extend('updateEditForm', $form);
		} else {
			$form = $this->RootForm();
		}

		$form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
		if($form->Fields()->hasTabset()) $form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
		$form->addExtraClass('center ss-tabset ' . $this->BaseCSSClasses());
					
		return $form;
	}

	/**
	 * The fields for individual groups will be created through {@link Group->getCMSFields()}.
	 * 
	 * @return FieldList
	 */
	function RootForm() {
		$config = new GridFieldConfig_RecordEditor();
		$config->addComponent(new GridFieldExporter());
		$config->getComponentByType('GridFieldPopupForms')->setValidator(new Member_Validator());
		$memberList = new GridField('Members', 'All members', DataList::create('Member'), $config);
		$memberList->addExtraClass("members_grid");
		
		$fields = new FieldList(
			$root = new TabSet(
				'Root',
				new Tab('Members', singleton('Member')->i18n_plural_name(),
					$memberList,
					new LiteralField('MembersCautionText', 
						sprintf('<p class="caution-remove"><strong>%s</strong></p>',
							_t(
								'SecurityAdmin.MemberListCaution', 
								'Caution: Removing members from this list will remove them from all groups and the database'
							)
						)
					)
				),
				new Tab('Import', _t('SecurityAdmin.TABIMPORT', 'Import'),
					new LiteralField(
						'GroupImportFormIframe', 
						sprintf(
							'<iframe src="%s" id="GroupImportFormIframe" width="100%%" height="400px" border="0"></iframe>',
							$this->Link('groupimport')
						)
					)
				)
			),
			// necessary for tree node selection in LeftAndMain.EditForm.js
			new HiddenField('ID', false, 0)
		);
		
		$root->setTemplate('CMSTabSet');
		
		// Add roles editing interface
		if(Permission::check('APPLY_ROLES')) {
			$rolesField = new GridField(
				'Roles',
				false,
				DataList::create('PermissionRole'),
				GridFieldConfig_RecordEditor::create()
			);
			// $rolesCTF->setPermissions(array('add', 'edit', 'delete'));

			$rolesTab = $fields->findOrMakeTab('Root.Roles', _t('SecurityAdmin.TABROLES', 'Roles'));
			$rolesTab->push(new LiteralField(
				'RolesDescription', 
				''
			));
			$rolesTab->push($rolesField);
		}

		$actions = new FieldList();
		
		$this->extend('updateRootFormFields', $fields, $actions);
		
		$form = new Form(
			$this,
			'EditForm',
			$fields,
			$actions
		);
		$form->addExtraClass('cms-edit-form');
		
		return $form;
	}
	
	function AddForm() {
		$form = parent::AddForm();
		$form->Actions()->fieldByName('action_doAdd')->setTitle(_t('SecurityAdmin.ActionAdd', 'Add group'));
		
		return $form;
	}
	
	public function memberimport() {
		Requirements::clear();
		Requirements::css(SAPPHIRE_ADMIN_DIR . '/css/screen.css');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::css(SAPPHIRE_ADMIN_DIR . '/css/MemberImportForm.css');
		Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/jquery-entwine/dist/jquery.entwine-dist.js');
		Requirements::javascript(SAPPHIRE_ADMIN_DIR . '/javascript/MemberImportForm.js');
		
		return $this->renderWith('BlankPage', array(
			'Form' => $this->MemberImportForm(),
			'Content' => ' '
		));
	}
	
	/**
	 * @see SecurityAdmin_MemberImportForm
	 * 
	 * @return Form
	 */
	public function MemberImportForm() {
		$group = $this->currentPage();
		$form = new MemberImportForm(
			$this,
			'MemberImportForm'
		);
		$form->setGroup($group);
		
		return $form;
	}
		
	public function groupimport() {
		Requirements::clear();
		Requirements::css(SAPPHIRE_ADMIN_DIR . '/css/screen.css');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
		Requirements::css(SAPPHIRE_ADMIN_DIR . '/css/MemberImportForm.css');
		Requirements::javascript(SAPPHIRE_DIR . '/thirdparty/jquery-entwine/dist/jquery.entwine-dist.js');
		Requirements::javascript(SAPPHIRE_ADMIN_DIR . '/javascript/MemberImportForm.js');
		
		return $this->renderWith('BlankPage', array(
			'Content' => ' ',
			'Form' => $this->GroupImportForm()
		));
	}
	
	/**
	 * @see SecurityAdmin_MemberImportForm
	 * 
	 * @return Form
	 */
	public function GroupImportForm() {
		$form = new GroupImportForm(
			$this,
			'GroupImportForm'
		);
		
		return $form;
	}

	function getCMSTreeTitle() {
		return _t('SecurityAdmin.SGROUPS', 'Security Groups');
	}

	public function EditedMember() {
		if(Session::get('currentMember')) return DataObject::get_by_id('Member', (int) Session::get('currentMember'));
	}

	function providePermissions() {
		$title = _t("SecurityAdmin.MENUTITLE", LeftAndMain::menu_title_for_class($this->class));
		return array(
			"CMS_ACCESS_SecurityAdmin" => array(
				'name' => sprintf(_t('CMSMain.ACCESS', "Access to '%s' section"), $title),
				'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
				'help' => _t(
					'SecurityAdmin.ACCESS_HELP',
					'Allow viewing, adding and editing users, as well as assigning permissions and roles to them.'
				)
			),
			'EDIT_PERMISSIONS' => array(
				'name' => _t('SecurityAdmin.EDITPERMISSIONS', 'Manage permissions for groups'),
				'category' => _t('Permissions.PERMISSIONS_CATEGORY', 'Roles and access permissions'),
				'help' => _t('SecurityAdmin.EDITPERMISSIONS_HELP', 'Ability to edit Permissions and IP Addresses for a group. Requires the "Access to \'Security\' section" permission.'),
				'sort' => 0
			),
			'APPLY_ROLES' => array(
				'name' => _t('SecurityAdmin.APPLY_ROLES', 'Apply roles to groups'),
				'category' => _t('Permissions.PERMISSIONS_CATEGORY', 'Roles and access permissions'),
				'help' => _t('SecurityAdmin.APPLY_ROLES_HELP', 'Ability to edit the roles assigned to a group. Requires the "Access to \'Users\' section" permission.'),
				'sort' => 0
			)
		);
	}
	
	/**
	 * The permissions represented in the $codes will not appearing in the form
	 * containing {@link PermissionCheckboxSetField} so as not to be checked / unchecked.
	 * 
	 * @param $codes String|Array
	 */
	static function add_hidden_permission($codes){
		if(is_string($codes)) $codes = array($codes);
		self::$hidden_permissions = array_merge(self::$hidden_permissions, $codes);
	}
	
	/**
	 * @param $codes String|Array
	 */
	static function remove_hidden_permission($codes){
		if(is_string($codes)) $codes = array($codes);
		self::$hidden_permissions = array_diff(self::$hidden_permissions, $codes);
	}
	
	/**
	 * @return Array
	 */
	static function get_hidden_permissions(){
		return self::$hidden_permissions;
	}
	
	/**
	 * Clear all permissions previously hidden with {@link add_hidden_permission}
	 */
	static function clear_hidden_permissions(){
		self::$hidden_permissions = array();
	}
}

/**
 * Delete multiple {@link Group} records. Usually used through the {@link SecurityAdmin} interface.
 * 
 * @package cms
 * @subpackage batchactions
 */
class SecurityAdmin_DeleteBatchAction extends CMSBatchAction {
	function getActionTitle() {
		return _t('AssetAdmin_DeleteBatchAction.TITLE', 'Delete groups');
	}

	function run(SS_List $records) {
		$status = array(
			'modified'=>array(),
			'deleted'=>array()
		);
		
		foreach($records as $record) {
			// TODO Provide better feedback if permission was denied
			if(!$record->canDelete()) continue;
			
			$id = $record->ID;
			$record->delete();
			$status['deleted'][$id] = array();
			$record->destroy();
			unset($record);
		}

		return Convert::raw2json($status);
	}
}

