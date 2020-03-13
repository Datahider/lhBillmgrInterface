<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once __DIR__ . '/../interface/lhUserRegistratorInterface.php';

/**
 * Description of lhUserRegistrator
 *
 * @author user
 */
class lhUserRegistrator implements lhUserRegistratorInterface {
    private $user;


    public function __construct(...$args) {
        if ((count($args) == 1)&&($args[0] == '__TEST__')) {
            $args = ['Василий Иванов', uniqid().'@losthost.online', '+79233355353', 182, 'sH8skfasdf1'];
            $this->_test();
        }
        
        if (count($args) != 5) { throw new Exception('lhUserRegistrator needs 5 parameters ($real_name, $email, $phone, $country, $password)'); }
        $good_real_name = $this->getGoodName($args[0]);
        $good_email = (new lhEmailValidator($args[1]))->getValid();
        $good_phone = (new lhPhoneValidator($args[2]))->getValid();
        $good_country = (int)$args[3] ? (int)$args[3] : 182;
        if ((strlen($args[4]) < 6)) { throw new Exception("Password must be at least 6 character long"); }
        $good_password = $args[4];
        
        $this->registerClient($good_real_name, $good_email, $good_phone, $good_country, $good_password);
        $this->user = new lhUser(lhUser::EMAIL, $good_email);
    }
    
    public function user() {
        return $this->user;
    }
    
    private function registerClient($_real_name, $_email, $_phone, $_country, $_password) {
        global $lhwebapi;
        
        // Создаем нового клиента
        $r = new SimpleXMLElement($lhwebapi->apiPost('account.edit', [
            'realname' => $_real_name,
            'email' => $_email,
            'phone' => $_phone,
            'country' => $_country,
            'passwd' => $_password,
            'confirm' => $_password,
            'verify_email' => 'off',
            'notify' => 'off',
            'sok' => 'ok'
        ], 'xml'));
        if (!isset($r->ok)) {
            throw new Exception("Error executing account.edit for adding a new user\n".$r->error->msg, -10004);
        }
        
        // Найдем пользователя по адресу почты
        $client_id = (int)$r->id;
        $r = new SimpleXMLElement($lhwebapi->apiPost('user.lookup', [
            'by' => 'email',
            'text' => $_email,
            'level' => 16
        ], 'xml'));
        if (!isset($r->id)) {
            throw new Exception("Can't lookup created user\n".print_r($r, TRUE), -10004);
        }
        
        // Установим ему номер телефона
        $user_id = (int)$r->id;
        $r = new SimpleXMLElement($lhwebapi->apiPost('user.edit', [
            'sok' => 'ok',
            'phone' => $_phone,
            'elid' => $user_id
        ], 'xml'));
        if (!isset($r->ok)) {
            throw new Exception("Can't lookup created user\n".$r->error->msg, -10004);
        }
        
        return $user_id;
    }
    
    private function getGoodName($param) {
        $names = mb_split("\s+", $param);
        $validator = new lhNameValidator();
        $count = count($names);

        for ($i=0;$i<$count;$i++) {
            $names[$i] = preg_replace_callback("/^(.)(.*)$/u", function ($matches) {
                return mb_strtoupper($matches[1], "UTF-8") . mb_strtolower($matches[2], "UTF-8");
            }, $names[$i]);
        }

        for ($i=0;$i<$count;$i++) {
            $name = array_shift($names);
            if ($validator->validate($name)) {
                $name = $validator->moreInfo()['full'];
                array_unshift($names, $name);
                return implode(' ', $names);
            }
            array_push($names, $name);
        }
        throw new Exception("Can't find known name for $param", -10003);
        
    }
    
    private function _test_data() {
        $test = [
            'getGoodName' => [
                ["sergio rodrigues", new Exception("Can't find name", -10003)],
                ["Иван Сидорович Петров", "Иван Сидорович Петров"],
                ["Иван петров", "Иван Петров"],
                ["петров ивАн", "Иван Петров"],
                ["Оглы бублы", new Exception("Invalid name", -10003)]
            ],
            'user' => '_test_user',
            'registerClient' => '_test_skip_',
        ];
        return $test;
    }

    private function _test() {
        $class_name = get_class();
        foreach (get_class_methods($class_name) as $key) {
            echo "Функция $key.";
            if (preg_match("/^_test/", $key) || preg_match("/__construct/", $key)) { 
                echo ".. пропущена\n";
                continue; 
            }
            
            $test_data = $this->_test_data();
            if (!isset($test_data[$key])) {
                throw new Exception("No test definition for member function $key");
            }
            $test_args = $test_data[$key];
            
            
            if (!is_array($test_args)) {
                if (!preg_match("/^_test/", $test_args)) {
                    throw new Exception("Test function name for $class_name::$key() must start with _test");
                }
                $func = $test_args;
                $this->$func();
                echo ". ok\n";
            } else {
                foreach ($test_args as $args) {
                    $await = array_pop($args);
                    try {
                        $result = $this->$key(...$args);
                        if (is_a($await, 'Exception')) {
                            throw new Exception("Awaiting an Exception with code: ".$await->getCode()." but did not got it", -907);
                        }
                        if ($result != $await) {
                            throw new Exception("Wrong result: $result, awaiting: $await", -907);
                        }
                    } catch (Exception $e) {
                        if ($e->getCode() == -907) throw $e;
                        if (!is_a($await, "Exception") || ($e->getCode() != $await->getCode()) ) {
                            throw new Exception("Invalid Exception with code: (".$e->getCode().") ".$e->getMessage());
                        }
                    }
                    echo '.';
                }
                echo ". ok\n";
            }
        }
        return TRUE;
    }
    
    private function _test_user() {
        $tmp_user = $this->user;
        $test_array = ["lkjsdaf hkhkk", 78, 2342, "lllllla sdafallasd f"];
        foreach ($test_array as $value) {
            $this->user = $value;
            $result = $this->user();
            if ($result != $value) {
                throw new Exception("lhUserRegistrator::user() returned $result, awaiting $value.", -10002);
            }
            echo '.';
        }
        $this->user = $tmp_user;
    }
    
    private function _test_skip_() {
        echo '.пропущено.';
    }

    private function _test_registerClient() {
        try {
            $this->registerClient("Василий Иванов", "boss@o3000.ru", '+7 (926) 226-18-68', 182, "123");
            throw new Exception("Awaiting exception", -907);
        } catch (Exception $ex) {
            if ($ex->getCode() == -907) throw $ex;
        }
        echo '.';

        try {
            $this->registerClient("Василий Иванов", "boss@o3000.ru", '+7 (926) 226-18-68', 182, "Zbue2s528wcqZR");
            throw new Exception("Awaiting exception", -907);
        } catch (Exception $ex) {
            if ($ex->getCode() == -907) throw $ex;
        }
        echo '.';
        
        $r = $this->registerClient("Василий Иванов", uniqid()."@o3000.ru", '+7 (926) 226-18-68', 182, "Zbue2s528wcqZR");
        if ((int)$r !== $r) {
            throw new Exception("Got $r, awaiting an integer");
        }
        echo ".$r.";
        
    }
}
