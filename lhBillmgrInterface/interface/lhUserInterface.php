<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhUserInterface
 *
 * @author user
 */
interface lhUserInterface {
    public function __construct($criteria, $value, $subset);
    public function userData();
    public function id();
}
