<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhTicketInterface
 *
 * @author user
 */
interface lhTicketInterface {
    public function __construct($ticket_id, $user=NULL, $subject=NULL, $message=NULL); // Создание инстанса тикета по ID или спец значениям

    // GET
    public function ticketData();
    public function id();
    
    // POST
    public function addNote(lhUser $user, $note);
    public function addMessage(lhUser $user, lhSimpleMessage $message);
    public function setResume($resume);
    public function delete();
}
