<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhTicket
 *
 * @author user
 */
require_once __DIR__ . '/../interface/lhTicketInterface.php';


class lhTicket implements lhTicketInterface {
    const CREATE = -1;
    const ACTIVE = -2;
    const ACTIVE_OR_NEW = -3;

    private $id;
    private $ticket_data;
    private $deleted;
    private $reset_auth;
            
    function __construct($ticket_id, $user=NULL, $subject=NULL, $message=NULL) {
        $this->deleted = FALSE;
        switch ($ticket_id) {
            case lhTicket::CREATE:
                $this->createTicket($user, $subject, $message);
                break;
            case lhTicket::ACTIVE: 
                $this->activeTicket($user);
                break;
            case lhTicket::ACTIVE_OR_NEW:
                $this->activeOrNew($user, $subject, $message);
                break;
            default:
                $this->loadTicket($ticket_id);
        }
        $this->reset_auth = FALSE;
    }
    
    public function ticketData() {
        $this->checkState();
        return $this->ticket_data;
    }
    
    public function id() {
        $this->checkState();
        return $this->id;
    }
    
    public function setResume($resume) {
        global $lhwebapi;
        $this->checkState();
 
        $response = new SimpleXMLElement($lhwebapi->apiPost('support_tool_note', [
            'summary' => $resume,
            'elid' => $this->id(),
            'sv_field' => 'save'
        ], 'xml'));
        if (!isset($response->show_success) || ((string)$response->show_success != 'yes') ) {
            throw new Exception("Can't set summaty for ticket ".$this->id()."\n".print_r($response->error, TRUE));
        }
    }

    public function addMessage(lhUser $user, lhSimpleMessage $message) {
        $this->checkState();
        $level = $user->userData()['level'];
        switch ($level) {
            case lhUser::LEVEL_USER:
                $this->addMessageFromUser($user, $message);
                break;
            case lhUser::LEVEL_AGENT:
                $this->addMessageFromAgent($user, $message);
                break;
            default:
                throw new Exception("Can only add messages from user or agent");
        }
    }
    
    private function addMessageFromUser(lhUser $user, lhSimpleMessage $message) {
        global $lhwebapi;
        $this->authAsUser($user);
        
        $form_data = $this->prepareMessage($message);
        $form_data['elid'] = $this->id();
        $response = new SimpleXMLElement($lhwebapi->apiPost('clientticket.edit', $form_data, 'xml'));
        if (!isset($response->ok)) {
            $lhwebapi->apiPost('chlevel', [ 'lp' => 1 ]); // Попытаемся вернуться на уровень наверх прежде чем вызывать исключение
            throw new Exception("Can't post message by user id ".$user->id());
        }
        
        $this->backToRoot();
    }

    private function addMessageFromAgent(lhUser $user, lhSimpleMessage $message) {
        global $lhwebapi;
        $this->authAsUser($user);
        $assignment = $this->getTicketAssignment();
        
        $form_data = $this->prepareMessage($message);
        $form_data['su'] = $user->id();
        $form_data['elid'] = $assignment;
        $form_data['plid'] = $this->id();
        $response = new SimpleXMLElement($lhwebapi->apiPost('ticket.edit', $form_data, 'xml'));

        if (!isset($response->ok)) {
            $lhwebapi->apiPost('chlevel', [ 'lp' => 1 ]); // Попытаемся вернуться на уровень наверх прежде чем вызывать исключение
            throw new Exception("Can't add message by user id ".$user->id()."\n".print_r($response->error));
        }
        
        $this->backToRoot();
    }

    private function prepareMessage(lhSimpleMessage $msg) {
        $data = [
            'sok' => 'ok',
            'clicked_button' => 'ok',
            'message' => $msg->text()
        ];
        
        $att_number = 0;
        foreach ($msg->attachments() as $att) {
            $att_number++;
            $data['file_'.$att_number] = new CURLFile($att->file());
            if (empty($data['message'])) {
                $data['message'] = "Информация во вложении";
            }
        }
        return $data;
    }

    public function addNote(lhUser $user, $note) {
        global $lhwebapi;
        $this->checkState();
        $this->authAsUser($user);
        $assignment = $this->getTicketAssignment();
        
        $response = new SimpleXMLElement($lhwebapi->apiPost('ticket.edit', [
            'sok' => 'ok',
            'show_optional' => 'on',
            'clicked_button' => 'ok',
            'note_message' => $note,
            'elid' => $assignment,
            'plid' => $this->id()
        ], 'xml'));
        
        if (!isset($response->ok)) {
            throw new Exception("Can't add ticket note to ticket ".$this->id()."\n".print_r($response, TRUE));
        }
        
        $this->backToRoot();
    }

    private function authAsUser(lhUser $user) {
        global $lhwebapi;
        $response = new SimpleXMLElement($lhwebapi->apiCall('su', [
            'elid' => $user->id()
        ], 'xml'));
        if (!isset($response->ok)) {
            throw new Exception("Can't switch to user id ".$user->id()."Server response follows\n".print_r($response, TRUE));
        }
        $new_auth = (string)$response->auth;
        if (!$new_auth) {
            throw new Exception("Empty auth received for user id ".$user->id());
        }
        $lhwebapi->setAuth($new_auth);
    }
    
    private function backToRoot() {
        global $lhwebapi;
        $response = new SimpleXMLElement($lhwebapi->apiCall('chlevel', [ 'lp' => 1 ], 'xml'));
        if (!isset($response->ok)) {
            throw new Exception("Can't switch back to root");
        }
        $lhwebapi->resetAuth();
    }
    
    private function getTicketAssignment() {
        global $lhwebapi;
        $response = new SimpleXMLElement($lhwebapi->apiPost('ticket_all.edit', [
            'plid' => $this->id(),
            'elid' => $this->id(),
            'clicked_button' => 'ok_get',
            'sok' => 'ok',
        ], 'xml'));
        
        $form_address = (string)$response->ok;
        if (!$form_address) {
            throw new Exception("Can't get ticket id ".$this->id()."Server response follows\n".print_r($response, TRUE));
        }
        if (!preg_match("/elid=(\d+)/", $form_address, $match)) {
            throw new Exception("Can't find assignment for ticket id ".$this->id());
        }
        return $match[1];
    }

    public function delete() {
        $this->checkState();
        global $lhwebapi;
        $response = new SimpleXMLElement($lhwebapi->apiCall('ticket_all.delete', [
            'elid' => $this->id()
        ], 'xml'));
        if (!isset($response->ok)) {
            throw new Exception("Error deleting ticket ".$this->id());
        }
        $this->deleted = TRUE;
    }
    
    private function createTicket($user, $subject, $message) {
        global $lhwebapi;
        $this->authAsUser($user);
        $data = $this->prepareMessage($message);
        $data['subject'] = $subject;
        
        $response = new SimpleXMLElement($lhwebapi->apiPost('clientticket.edit', $data, 'xml'));
        $this->backToRoot();
        if (!isset($response->ok)) {
            throw new Exception("Can't create new ticket\n".print_r($response, TRUE));
        }
        $id = (int)$response->id;
        if (!$id) {
            throw new Exception("Can't get new ticket id\n".print_r($response, TRUE));
        }
        $this->loadTicket($id);
    }
    
    private function activeTicket($user) {
        global $lhwebapi;
        $response = json_decode($lhwebapi->apiCall('ticket.active', [
            'elid' => $user->id()
        ]));
        if (empty($response->doc->id->{'$'})) {
            throw new Exception("There is no active tickets for user ".$user->id(), -1);
        }
        $this->loadTicket($response->doc->id->{'$'});
    }
    
    private function activeOrNew($user, $subject, $message) {
        try {
            $this->activeTicket($user);
        } catch (Exception $ex) {
            if ($ex->getCode() == -1) {
                $this->createTicket($user, $subject, $message);
            } else {
                throw $ex;
            }
        }
    }

    private function loadTicket($ticket_id) {
        global $lhwebapi;
        $this->ticket_data = (array) new SimpleXMLElement($lhwebapi->apiCall('ticket_all.edit', [
            'elid' => $ticket_id
        ], 'xml'));
        $this->id = $this->ticket_data['id'];
    }
    
    private function checkState() {
        if ($this->deleted) {
            throw new Exception("Object is deleted");
        }
    }
}
