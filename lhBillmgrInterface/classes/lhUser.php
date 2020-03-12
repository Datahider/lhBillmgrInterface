<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhUser
 *
 * @author user
 */
require_once __DIR__ . '/../interface/lhUserInterface.php';

require_once LH_LIB_ROOT . '/lhValidator/classes/lhPhoneValidator.php';
require_once LH_LIB_ROOT . '/lhValidator/classes/lhEmailValidator.php';

class lhUser implements lhUserInterface {
    // Возможные значения критерия отбора
    const ID = 'id';
    const PHONE = 'phone';
    const EMAIL = 'email';
    
    // Возможные значения подмножества пользователей
    const LEVEL_AGENT = 29;
    const LEVEL_USER = 16;
    const LEVEL_DEPT = 28;
    
    private $user_data;

    function __construct($criteria, $value, $subset=self::LEVEL_USER) {
        global $lhwebapi;
        $param = $this->valueCheck($criteria, $value);

        if ( $criteria != self::ID ) {
            $data = new SimpleXMLElement($lhwebapi->apiCall('user.lookup', [
                'by' => $criteria,
                'text' => $param,
                'level' => $subset
            ], 'xml'));
            $id = (int)$data->id;
        } else {
            $id = $value;
        }

        if (empty($id)) {
            throw new Exception("User not found", -1);
        }
        
        $this->user_data = (array) new SimpleXMLElement($lhwebapi->apiCall('user.edit', [
            'elid' => $id
        ], 'xml'));
        if (!isset($this->user_data['id'])) { throw new Exception("User not found", -2); }
        
    }
    
    public function userData() {
        return $this->user_data;
    }
    
    public function id() {
        if (!isset($this->user_data['id'])) { throw new Exception("id пуст"); }
        return (int)$this->user_data['id'];
    }

    private function generateSQL($criteria, $subset) {
        if ($criteria != lhUser::ID) {
            $sql = $this->generateCriteriaRequest($criteria) 
                    . $this->subsetCond($subset);
        } else {
            $sql = $this->generateCriteriaRequest($criteria);
        }
        return $sql;
    }

    private function generateCriteriaRequest($criteria) {
        switch ($criteria) {
            case lhUser::ID:
                $sql = 'SELECT user.* FROM user WHERE user.id = :param';
                break;
            case lhUser::TELEGRAM_CHAT_ID:
                $sql = 'SELECT user.* FROM user INNER JOIN lhextraparam '
                    . 'ON user.id = lhextraparam.entity_id '
                    . 'WHERE lhextraparam.entity = "user" '
                    . 'AND lhextraparam.value = :param ';
                break;
            case lhUser::PHONE_NUMBER:
                $sql = 'SELECT user.* FROM user WHERE user.phone = :param';
                break;
            case lhUser::EMAIL:
                $sql = 'SELECT user.* FROM user WHERE user.email = :param';
                break;
            default:
                throw new Exception("Unknown user criteria: $criteria");
        }
        return $sql;
    }
    
    private function subsetCond($subset) {
        switch ($subset) {
            case lhUser::AN_AGENT:
                $sql = ' AND user.level = 29';
                break;
            case lhUser::A_USER:
                $sql = ' AND user.level = 16';
                break;
            case lhUser::A_DEPT:
                $sql = ' AND user.level = 28';
                break;
            default:
                throw new Exception("Unknown user subset: $subset");
        }
        return $sql;
    }
    
    private function valueCheck($criteria, $value) {
        switch ($criteria) {
            case lhUser::PHONE:
                $pv = new lhPhoneValidator();
                if ($pv->validate($value)) {
                    $result = $pv->moreInfo()['phone'];
                } else {
                    throw new Exception("Invalid phone number: $value");
                }
                break;
            case lhUser::EMAIL:
                $ev = new lhEmailValidator();
                if ($ev->validate($value)) {
                    $result = $ev->moreInfo()['address'];
                } else {
                    throw new Exception("Invalid e-mail address: $value");
                }
                break;
            default:
                $result = $value;
        }
        return $result;
    }
}
