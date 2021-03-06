<?php
/*****************************************************************************
*       AdminPersonsController.php
*
*       Author:  ClearHealth Inc. (www.clear-health.com)        2009
*       
*       ClearHealth(TM), HealthCloud(TM), WebVista(TM) and their 
*       respective logos, icons, and terms are registered trademarks 
*       of ClearHealth Inc.
*
*       Though this software is open source you MAY NOT use our 
*       trademarks, graphics, logos and icons without explicit permission. 
*       Derivitive works MUST NOT be primarily identified using our 
*       trademarks, though statements such as "Based on ClearHealth(TM) 
*       Technology" or "incoporating ClearHealth(TM) source code" 
*       are permissible.
*
*       This file is licensed under the GPL V3, you can find
*       a copy of that license by visiting:
*       http://www.fsf.org/licensing/licenses/gpl.html
*       
*****************************************************************************/


class AdminPersonsController extends WebVista_Controller_Action {
	protected $_form;
	protected $_person;

	public function indexAction() {
	}

	public function mainPaneAction() {
	}

	public function autoCompleteAction() {
        	$match = $this->_getParam('name');
		$match = preg_replace('/[^a-zA-Z-0-9]/','',$match);
		$matches = array();
		if (!strlen($match) > 0) $this->_helper->autoCompleteDojo($matches);
		$db = Zend_Registry::get('dbAdapter');
		$match = $db->quote($match.'%');
		$patSelect = $db->select()
				->from('person')
				->joinLeftUsing('provider','person_id')
				->joinLeftUsing('user','person_id')
				->where('person.last_name LIKE '.$match)
				->orWhere('person.first_name LIKE '.$match)
				->orWhere('user.username LIKE '.$match)
				->order('person.last_name DESC')
				->order('person.first_name DESC');
				//->limit(50);

		$dbStmt = $db->query($patSelect);

		$excludeColumns = array();
		$excludeColumns['provider'] = array('person_id');
		$excludeColumns['user'] = array('person_id');

		$data = array();
		$offset = 0;
		$rowCount = $dbStmt->rowCount();
		for ($offset = 0; $offset < $rowCount; $offset++) {
			//$row = $dbStmt->fetch(null,null,$offset);
			$row = $dbStmt->fetch(PDO::FETCH_NUM,null,$offset);
			$columnMeta = array();
			$columnMetaCount = 0;
			if ($offset == 0) {
				for($i = 0, $ctr = count($row); $i < $ctr; $i++) {
					$columnMeta[$i] = $dbStmt->getColumnMeta($i);
					$columnMetaCount++;
				}
			}
			$tmp = array();
			for ($col = 0; $col < $columnMetaCount; $col++) {
				if (isset($excludeColumns[$columnMeta[$col]['table']]) && in_array($columnMeta[$col]['name'],$excludeColumns[$columnMeta[$col]['table']])) {
					continue;
				}
				$tmp[$columnMeta[$col]['name']] = $row[$col];
			}
			if (isset($tmp['person_id'])) {
				$data[] = $tmp;
			}
		}
		$dbStmt->closeCursor();

		foreach ($data as $row) {
			$matches[$row['person_id']] = $row['last_name'] . ', ' . $row['first_name'] . ' ' . substr($row['middle_name'],0,1) . ' (' . $row['username'] .")"; 
		}

        	$this->_helper->autoCompleteDojo($matches);
	}

	public function editAction() {
		$personId = (int)$this->_getParam('personId');
        	if (isset($this->_session->messages)) {
        	    $this->view->messages = $this->_session->messages;
        	}
		$this->_form = new WebVista_Form(array('name' => 'person-detail'));
		$this->_form->setAction(Zend_Registry::get('baseUrl') . "admin-persons.raw/edit-process");
		$this->_person = new Person();
		$this->_person->person_id = $personId;
		$this->_person->populate();
		$this->_form->loadORM($this->_person, "Person");
		//var_dump($this->_form);
		$this->view->form = $this->_form;
		$this->view->person = $this->_person;

		$practices = array(''=>'');
		$facilityIterator = new FacilityIterator();
		$facilityIterator->setFilter(array('Practice'));
		foreach ($facilityIterator as $practice) {
			$practices[$practice->id] = $practice->name;
		}
		$this->view->practices = $practices;

		$genders = array(''=>'');
		$gender = Enumeration::getEnumArray('Gender','enumerationId');
		foreach ($gender as $key=>$value) {
			$genders[$key] = $value;
		}
		$this->view->genders = $genders;

		$maritalStatuses = array(''=>'');
		$maritalStatus = Enumeration::getEnumArray('Marital Status','enumerationId');
		foreach ($maritalStatus as $key=>$value) {
			$maritalStatuses[$key] = $value;
		}
		$this->view->maritalStatuses = $maritalStatuses;

		$this->view->statesList = Address::getStatesList();
        	$this->render('edit-person');
	}

	public function editProcessAction() {
		$params = $this->_getParam('person');
		$personId = (int)$params['personId'];
		$this->_person = new Person();
		$this->_person->populateWithArray($params);
		$this->_person->persist();
		/*
		$this->_provider = new Provider();
		if ($personId > 0) {
			$this->_provider->person->personId = $personId;
			$this->_provider->person->populate();
		}
		$this->_provider->person->populateWithArray($params);
		$this->_provider->person->persist();
		*/
		$json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
                $json->suppressExit = true;
		$msg = "Record Saved for Person: " . ucfirst($this->_person->firstName) . " " . ucfirst($this->_person->lastName);
                $json->direct($msg);
	}

	public function ajaxListPhonesAction() {
		$personId = (int)$this->_getParam('personId');
		$rows = array();
		$tmp = array();
		$db = Zend_Registry::get('dbAdapter');
		$sqlSelect = $db->select()
				->from('number')
				->where('person_id = ?',$personId);
		$phoneNumberIterator = new PhoneNumberIterator();
		$phoneNumberIterator->setDbSelect($sqlSelect);
		foreach ($phoneNumberIterator as $phone) {
			$tmp = array();
			$tmp['id'] = $phone->number_id;
			$tmp['data'][] = $phone->name;
			$tmp['data'][] = $phone->type;
			$tmp['data'][] = $phone->number;
			$tmp['data'][] = $phone->notes;
			$tmp['data'][] = $phone->active;
			$rows[] = $tmp;
		}
		$json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$json->suppressExit = true;
		$json->direct(array('rows'=>$rows));
	}

	public function ajaxListAddressesAction() {
		$personId = (int)$this->_getParam('personId');
		$rows = array();
		$db = Zend_Registry::get('dbAdapter');
		$sqlSelect = $db->select()
				->from('address')
				->where('person_id = ?',$personId);
		$addressIterator = new AddressIterator();
		$addressIterator->setDbSelect($sqlSelect);
		foreach ($addressIterator as $addr) {
			$tmp = array();
			$tmp['id'] = $addr->address_id;
			$tmp['data'][] = $addr->name;
			$tmp['data'][] = $addr->type;
			$tmp['data'][] = $addr->line1;
			$tmp['data'][] = $addr->line2;
			$tmp['data'][] = $addr->city;
			//$tmp['data'][] = $addr->region;
			$tmp['data'][] = $addr->state;
			$tmp['data'][] = $addr->postal_code;
			$tmp['data'][] = $addr->notes;
			$tmp['data'][] = $addr->active;
			$rows[] = $tmp;
		}
		$json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$json->suppressExit = true;
		$json->direct(array('rows'=>$rows));
	}

	public function ajaxGetContextMenuAction() {
		header('Content-Type: application/xml;');
		$this->render();
	}

	public function processEditByFieldAction() {
		$personId = (int)$this->_getParam('personId');
		$type = $this->_getParam('type');
		$id = (int)$this->_getParam('id');
		$field = $this->_getParam('field');
		$value = $this->_getParam('value');

		$obj = null;
		switch ($type) {
			case 'address':
				$obj = new Address();
				$obj->person_id = $personId;
				break;
			case 'phone':
				$obj = new PhoneNumber();
				$obj->person_id = $personId;
				break;
			default:
				break;
		}

		$retVal = false;
		if ($obj !== null && in_array($field,$obj->ormFields())) {
			if ($id > 0) {
				foreach ($obj->_primaryKeys as $k) {
					$obj->$k = $id;
				}
				$obj->populate();
			}
			$obj->$field = $value;
			$obj->persist();
			$retVal = true;
		}
		if ($retVal) {
			$data = true;
		}
		else {
			$data = array('error' => __('There was an error attempting to update the selected record.'));
		}
		$json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$json->suppressExit = true;
		$json->direct($data);
	}

	public function processDeleteAction() {
		$personId = (int)$this->_getParam('personId');
		$type = $this->_getParam('type');
		$id = (int)$this->_getParam('id');

		$obj = null;
		switch ($type) {
			case 'address':
				$obj = new Address();
				break;
			case 'phone':
				$obj = new PhoneNumber();
				break;
			default:
				break;
		}

		$retVal = false;
		if ($obj !== null && $id > 0) {
			foreach ($obj->_primaryKeys as $k) {
				$obj->$k = $id;
			}
			$obj->setPersistMode(WebVista_Model_ORM::DELETE);
			$obj->persist();
			$retVal = true;
		}
		if ($retVal) {
			$data = true;
		}
		else {
			$data = array('error' => __('There was an error attempting to delete the selected record.'));
		}
		$json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$json->suppressExit = true;
		$json->direct($data);
	}

}
