<?php

/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class PBXManager_PBXManager_Controller {

    function getConnector() {
        return new PBXManager_PBXManager_Connector;
    }

    /**
     * Function to process the request
     * @params <array> call details
     * return Response object
     */
    function process($request) {
        $mode = $request->get('callstatus');

        switch ($mode) {
            case "StartApp" :
                $this->processStartupCall($request);
                break;
            case "DialAnswer" :
                $this->processDialCall($request);
                break;
            case "Record" :
                $this->processRecording($request);
                break;
            case "EndCall" :
                $this->processEndCall($request);
                break;
            case "Hangup" :
                $callCause = $request->get('causetxt');
                if ($callCause == "null" || empty($callCause)) {
                    break;
                }
                $this->processHangupCall($request);
                break;                
            case "DialBegin" :
                $this->processDialBeginCall($request);
                break;
        }
    }

    // begin alternative start call detection mode
    /**
     * Function to process Incoming call request
     * @params <array> incoming call details
     * return Response object
     */
    function processDialBeginCall($request) {
        $callerNumber = $request->get('callerIdNumber');
        
        /* Get dialed number by caller. It has unified format so we need check variants */
        $destinationNumber = '';
        if(strpos($request->get('dialString'), "/") !== false) {
            $dialParts = explode("/", $request->get('dialString'));
            $destinationNumber = end($dialParts);
        } elseif(strpos($request->get('dialString'), "@") !== false) {
            $dialParts = explode("@", $request->get('dialString'));
            $destinationNumber = $dialParts[0];
        } else {
            $destinationNumber = $request->get('dialString');
        }
        
        /* If not Originate event - prepare begin of call */
        if($callerNumber != $destinationNumber && !empty($destinationNumber) && !empty($callerNumber)) {
            $callerUserInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);
   
            /* If caller number binded with crm user - it outgoing number */
            $connector = $this->getConnector();
            $log['time']  = $request->get('StartTime');
            $log['from']       = $callerNumber;
            $log['to']  = $destinationNumber;
            if ($callerUserInfo) {
                $request->set('Direction', 'outbound');
                $request->set('to', $destinationNumber);
                $customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($destinationNumber, $callerUserInfo['id'],'outbound');
                $record = $request->get('record');
                if (!empty($record)) {
                    $customerInfo = PBXManager_Record_Model::lookUpRelatedWithRecord($record, $callerUserInfo['id']);                    
                }
                $from_details   = $callerUserInfo;
                $to_details     = $customerInfo;
                if(!is_array($customerInfo)){
                    $connector->log($destinationNumber . ' not found in vtiger. Outbound call ignored.' , 'PBXManager-process');
                    return;
                }
                $connector->handleStartupCall($request, $callerUserInfo, $customerInfo);
            } else {

                /* If no match of twon numbers for crm users - don't fix ring */
                $crmUserInfo = PBXManager_Record_Model::getUserInfoWithNumber($destinationNumber);
                if(!$crmUserInfo) {
                    $connector->log('Destinantion ' . $destinationNumber . ' not found in vtiger. Call ignored.', 'PBXManager-process');
                    return;
                }
                $request->set('Direction', 'inbound');
                $request->set('from', $request->get('callerIdNumber'));
                $customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($request->get('callerIdNumber'), $crmUserInfo['id'],'inbound');
                if(!is_array($customerInfo)){
                    // Descomentar si se quiere ignorar las llamadas de clientes no registrados en CRM. No Popup de desconocidos.
                    // $connector->log('Inbound ' . $callerNumber . ' not found in vtiger. Call ignored.' , 'PBXManager-process');
                    // return;
                }
                $from_details   = $customerInfo;
                $to_details     = $crmUserInfo;
                $connector->handleStartupCall($request, $crmUserInfo, $customerInfo);
            }
            $log['from_details']= $from_details;
            $log['to_details']  = $to_details;
            $log['direction']   = $request->get('Direction');
            $connector->log($log , 'PBXManager-process');
        }
    }
    /**
     * Function to process Incoming call request
     * @params <array> incoming call details
     * return Response object
     */
    function processStartupCall($request) {
        $connector = $this->getConnector();

        $temp = $request->get('channel');
        $temp = explode("-", $temp);
        $temp = explode("/", $temp[0]);

        $callerNumber = $request->get('callerIdNumber');
        $userInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);

        if (!$userInfo) {
            $callerNumber = $temp[1];
            if (is_numeric($callerNumber)) {
                $userInfo = PBXManager_Record_Model::getUserInfoWithNumber($callerNumber);
            }
        }

        if ($userInfo) {
            // Outbound Call
            $request->set('Direction', 'outbound');

            if ($request->get('callerIdNumber') == $temp[1]) {
                $to = $request->get('callerIdName');
            } else if ($request->get('callerIdNumber')) {
                $to = $request->get('callerIdNumber');
            } else if ($request->get('callerId')) {
                $to = $request->get('callerId');
            }

            $request->set('to', $to);
            $customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($to);
            $connector->handleStartupCall($request, $userInfo, $customerInfo);
        } else {
            // Inbound Call
            $request->set('Direction', 'inbound');
            $customerInfo = PBXManager_Record_Model::lookUpRelatedWithNumber($request->get('callerIdNumber'));
            $request->set('from', $request->get('callerIdNumber'));
            $connector->handleStartupCall($request, $userInfo, $customerInfo);
        }
    }

    /**
     * Function to process Dial call request
     * @params <array> Dial call details
     * return Response object
     */
    function processDialCall($request) {
        $connector = $this->getConnector();
        $connector->handleDialCall($request);
    }

    /**
     * Function to process EndCall event
     * @params <array> Dial call details
     * return Response object
     */
    function processEndCall($request) {
        $connector = $this->getConnector();
        $connector->handleEndCall($request);
    }

    /**
     * Function to process Hangup call request
     * @params <array> Hangup call details
     * return Response object
     */
    function processHangupCall($request) {
        $connector = $this->getConnector();
        $connector->handleHangupCall($request);
    }

    /**
     * Function to process recording
     * @params <array> recording details
     * return Response object
     */
    function processRecording($request) {
        $connector = $this->getConnector();
        $connector->handleRecording($request);
    }

}