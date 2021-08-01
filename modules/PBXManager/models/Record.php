<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class PBXManager_Record_Model extends Vtiger_Record_Model{
    
    const moduletableName = 'vtiger_pbxmanager';
    const lookuptableName = 'vtiger_pbxmanager_phonelookup';
    const entitytableName = 'vtiger_crmentity';
    
    static function getCleanInstance($moduleName = ''){
        return new self;
    }
    
    /**
     * Function to get call details(polling)
     * return <array> calls
     */
    public function searchIncomingCall(){
        $db = PearDatabase::getInstance();
        $query = 'SELECT * FROM '.self::moduletableName.' AS module_table INNER JOIN '.self::entitytableName.' AS entity_table  WHERE module_table.callstatus IN(?,?) AND module_table.direction=? AND module_table.pbxmanagerid=entity_table.crmid AND entity_table.deleted=0';
        $result = $db->pquery($query,array('ringing','in-progress','inbound'));
        $recordModels = $recordIds = array();
        $rowCount =  $db->num_rows($result);
        for($i=0; $i<$rowCount; $i++) {
            $rowData = $db->query_result_rowdata($result, $i);
            
            $record = new self();
            $record->setData($rowData);
            $recordModels[] = $record;
            
            //To check if the call status is 'ringing' for >5min
            $starttime = strtotime($rowData['starttime']);
            $currenttime = strtotime(Date('y-m-d H:i:s'));
            $timeDiff = $currenttime - $starttime;
            if($timeDiff > 300 && $rowData['callstatus'] == 'ringing') {
                $recordIds[] = $rowData['crmid'];
            }
            //END
        }    
        
        if(count($recordIds)) $this->updateCallStatus($recordIds);
        
        return $recordModels;
    }
    
    /**
     * To update call status from 'ringing' to 'no-response', if status not updated 
     * for more than 5 minutes
     * @param type $recordIds
     */
    public function updateCallStatus($recordIds) {
        $db = PearDatabase::getInstance();
        $query = "UPDATE ".self::moduletableName." SET callstatus='no-response' 
                  WHERE pbxmanagerid IN (".generateQuestionMarks($recordIds).") 
                  AND callstatus='ringing'";
        $db->pquery($query, $recordIds);
    }

        /**
     * Function to save PBXManager record with array of params
     * @param <array> $values
     * return <string> $recordid
     */
    public function saveRecordWithArrray($params){
        $moduleModel = Vtiger_Module_Model::getInstance('PBXManager');
        $recordModel = Vtiger_Record_Model::getCleanInstance('PBXManager');
        $recordModel->set('mode', '');
        $details = array_change_key_case($params, CASE_LOWER);
        $fieldModelList = $moduleModel->getFields();
        foreach ($fieldModelList as $fieldName => $fieldModel) {
                $fieldValue = $details[$fieldName];
                $recordModel->set($fieldName, $fieldValue);
        }
        return $moduleModel->saveRecord($recordModel);
    }
    
    /**
     * Function to update call details
     * @param <array> $details
     * $param <string> $callid
     * return true
     */
    public function updateCallDetails($details, $user = null){
        $db = PearDatabase::getInstance();
        $sourceuuid = $this->get('sourceuuid');
        $query = 'UPDATE '.self::moduletableName.' SET ';
        foreach($details as $key => $value){
            $query .= $key . '=?,';
            $params[] = $value;
        }
        $query = substr_replace($query ,"",-1);
        $query .= ' WHERE sourceuuid = ?';
        $params[] = $sourceuuid;
        if($user) { 
            $query .= ' AND user = ?'; 
            $params[] = $user['id']; 
        }         
        $db->pquery($query, $params);
        return true;
    }
    
    /**
     * To update Assigned to with user who answered the call 
     */
    public function updateAssignedUser($userid){
        $callid = $this->get('pbxmanagerid');
        $db = PearDatabase::getInstance();
        $query = 'UPDATE '.self::entitytableName.' SET smownerid=? WHERE crmid=?';
        $params = array($userid, $callid);
        $db->pquery($query, $params);
        return true;
    }
    
    public static function getInstanceById($phonecallsid, $module=null){
        $db = PearDatabase::getInstance();
        $record = new self();
        $query = 'SELECT * FROM '.self::moduletableName.' WHERE pbxmanagerid=?';
        $params = array($phonecallsid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record->setData($rowData);
        }
        return $record;
    }
    
    public static function getInstanceBySourceUUID($sourceuuid, $user  = null){
        $db = PearDatabase::getInstance();
        $record = new self();
        $query = 'SELECT * FROM '.self::moduletableName.' WHERE sourceuuid=?';
        $params = array($sourceuuid);
        if($user) { 
            $query .= ' AND user=?'; 
            $params[] = $user['id']; 
        }
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record->setData($rowData);
        }
        return $record;
    }
    
    public static function updateCallRecordBySourceUUID($sourceuuid, $recordingUrl) {
        $db = PearDatabase::getInstance();
        $query = 'UPDATE '.self::moduletableName.' SET recordingurl=? WHERE sourceuuid=?';
        $db->pquery($query, array($recordingUrl, $sourceuuid));
    }
    
    /**
     * Returns pbx manager record model which matches for received end call details
     * @param string $sourceuuid
     * @return PBXManager_Record_Model
     */
    public static function getModelForEndCallDetails($sourceuuid) {
        $db = PearDatabase::getInstance();
        $query = "SELECT * FROM " . self::moduletableName . " WHERE sourceuuid=? AND callstatus IN('in-progress','completed')"; 
        
        $params = array($sourceuuid);
        $result = $db->pquery($query, $params);
        $rowCount =  $db->num_rows($result);
        if($rowCount){
            $rowData = $db->query_result_rowdata($result, 0);
            $record = new self();
            $record->setData($rowData);
            
            return $record;
        }
        
        return null;
    }
    
    /**
     * Updates call details of pbx manager record model by it's id
     * @param int $recordId
     * @param array $details
     */
    public static function updateCallDetailsByRecordId($recordId, $details) {
        $db = PearDatabase::getInstance();
        $query = 'UPDATE '.self::moduletableName.' SET ';
        $params = array();
        foreach($details as $key => $value){
            $query .= $key . '=?,';
            $params[] = $value;
        }
        $query = substr_replace($query ,"",-1);
        $query .= ' WHERE pbxmanagerid=?';
        $params[] = $recordId;
        
        $db->pquery($query, $params);
    }
    
    public static function updateCallDetailsBySourceUUID($sourceuuid, $details) {
        $db = PearDatabase::getInstance();
        $query = 'UPDATE '.self::moduletableName.' SET ';
        $params = array();
        foreach($details as $key => $value){
            $query .= $key . '=?,';
            $params[] = $value;
        }
        $query = substr_replace($query ,"",-1);
        $query .= ' WHERE sourceuuid = ?';
        $params[] = $sourceuuid;
        
        $db->pquery($query, $params);
    }   
    /**
     * Function to save/update contact/account/lead record in Phonelookup table on every save
     * @param <array> $details
     */
    public function receivePhoneLookUpRecord($fieldName, $details, $new){
        $recordid = $details['crmid'];
        $fnumber = preg_replace('/[-()\s+]/', '',$details[$fieldName]);
        $rnumber = strrev($fnumber);
        $db = PearDatabase::getInstance();
        
        /* Delete record if it null after filtering number */
        if($fnumber == '') {
            $db->pquery('DELETE FROM ' . self::lookuptableName . ' where crmid=? AND fieldname=? AND setype=?',
                    array($recordid, $fieldName, $details['setype']));
            return true;
        }        
        $params = array($recordid, $details['setype'],$fnumber,$rnumber, $fieldName);
        $db->pquery('INSERT INTO '.self::lookuptableName.
                    '(crmid, setype, fnumber, rnumber, fieldname) 
                    VALUES(?,?,?,?,?) 
                    ON DUPLICATE KEY 
                    UPDATE fnumber=VALUES(fnumber), rnumber=VALUES(rnumber)', 
                    $params);
        return true;
    }
    
     /**
      * Function to delete contact/account/lead record in Phonelookup table on every delete
      * @param <string> $recordid
      */
    public function deletePhoneLookUpRecord($recordid){
        $db = PearDatabase::getInstance();
        $db->pquery('DELETE FROM '.self::lookuptableName.' where crmid=?', array($recordid));
    }

     /**
      * * Function to check the customer with number in phonelookup table
      * @param <string> $from
      */
    public static function lookUpRelatedWithNumber($from, $callAssignedIserId = null, $direction){
        $db = PearDatabase::getInstance();
        $fnumber = preg_replace('/[-()\s+]/', '',$from);
        if($from == NULL) {
            return;
        }        
        /* Prepare entity search params and conditions */
        $numberSql = "?";
        $numberSqlArg = $fnumber;
        if(strlen($fnumber) >= 10) {
            $numberSql = '"%"?';
            $numberSqlArg = array(substr($fnumber, -10, 10));
        }
        
        $result = $db->pquery(
            'SELECT vtiger_crmentity.crmid AS id,vtiger_crmentity.label AS name,vtiger_crmentity.setype,vtiger_crmentity.smownerid, ' . 
            self::lookuptableName . '.fieldname FROM ' . self::lookuptableName . ' ' .
            'INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=' . self::lookuptableName . '.crmid ' .
            'WHERE ' . self::lookuptableName . '.fnumber LIKE ' . $numberSql . ' AND vtiger_crmentity.deleted=0', 
            array($numberSqlArg)
        );
        if ($db->num_rows($result)>1 && $direction == 'outbound') {
            return;
        }
        
        /* Search first entity data with match to assigned user if need */
        $callerEntityData = $db->fetchByAssoc($result);
        if($callAssignedIserId != null && $callerEntityData['smownerid'] != $callAssignedIserId) {
            while($row = $db->fetchByAssoc($result)) {
                if($row['smownerid'] == $callAssignedIserId) {
                    $callerEntityData = $row;
                    break;
                }
            }
        } 
        return $callerEntityData;
    }
        public static function lookUpRelatedWithRecord($record, $callAssignedIserId = null){
            $db = PearDatabase::getInstance();
            if($record == NULL) {
                return;
            }               
            $result = $db->pquery(
                'SELECT vtiger_crmentity.crmid AS id,vtiger_crmentity.label AS name,vtiger_crmentity.setype,vtiger_crmentity.smownerid, "mobile" as fieldname 
                FROM vtiger_crmentity 
                INNER JOIN vtiger_contactdetails ON vtiger_crmentity.crmid=vtiger_contactdetails.contactid 
                WHERE  vtiger_crmentity.deleted=0  AND vtiger_crmentity.crmid=' . $record
            );
            
            /* Search first entity data with match to assigned user if need */
            $callerEntityData = $db->fetchByAssoc($result);
            if($callAssignedIserId != null && $callerEntityData['smownerid'] != $callAssignedIserId) {
                while($row = $db->fetchByAssoc($result)) {
                    if($row['smownerid'] == $callAssignedIserId) {
                        $callerEntityData = $row;
                        break;
                    }
                }
            } 
            return $callerEntityData;
        }
    
     /**
      * Function to user details with number
      * @param <string> $number
      */
    public static function getUserInfoWithNumber($number){
        $db = PearDatabase::getInstance();
        if(empty($number)){
            return false;
        }
        $query = PBXManager_Record_Model::buildSearchQueryWithUIType(11, $number, 'Users');
        $result = $db->pquery($query, array());
        if($db->num_rows($result) > 0 ){
            $user['id'] = $db->query_result($result, 0, 'id');
            $user['name'] = $db->query_result($result, 0, 'name');
            $user['setype'] = 'Users';
            return $user;
        }
        return;
    }    
    
    // Because, User is not related to crmentity 
    public function buildSearchQueryWithUIType($uitype, $value, $module){
        if (empty($value)) {
            return false;
        }
        
        $cachedModuleFields = VTCacheUtils::lookupFieldInfo_Module($module);
        if ($cachedModuleFields === false) {
            getColumnFields($module); // This API will initialize the cache as well
            // We will succeed now due to above function call
            $cachedModuleFields = VTCacheUtils::lookupFieldInfo_Module($module);
        }

        $lookuptables = array();
        $lookupcolumns = array();
        foreach ($cachedModuleFields as $fieldinfo) {
            if (in_array($fieldinfo['uitype'], array($uitype))) {
                $lookuptables[] = $fieldinfo['tablename'];
                $lookupcolumns[] = $fieldinfo['columnname'];
            }
        }

        $entityfields = getEntityField($module);
        $querycolumnnames = implode(',', $lookupcolumns);
        $entitycolumnnames = $entityfields['fieldname'];

        $query = "select id as id, $querycolumnnames, $entitycolumnnames as name ";
        $query .= " FROM vtiger_users";

        if (!empty($lookupcolumns)) {
            $query .=" WHERE deleted=0 AND ";
            $i = 0;
            $columnCount = count($lookupcolumns);
            foreach ($lookupcolumns as $columnname) {
                if (!empty($columnname)) {
                    if ($i == 0 || $i == ($columnCount))
                        $query .= sprintf("%s = '%s'", $columnname, $value);
                    else
                        $query .= sprintf(" OR %s = '%s'", $columnname, $value);
                    $i++;
                }
            }
         }
         return $query;
    }

    public static function getUserNumbers(){
        $numbers = null;
        $db = PearDatabase::getInstance();
        $query = 'SELECT id, phone_crm_extension FROM vtiger_users';
        $result = $db->pquery($query, array());
        $count = $db->num_rows($result);
        for($i=0; $i<$count; $i++){
            $number = $db->query_result($result, $i, 'phone_crm_extension');
            $userId = $db->query_result($result, $i, 'id');
            if($number)
                $numbers[$userId] = $number;
        }
        return $numbers;
    }
}
?>
