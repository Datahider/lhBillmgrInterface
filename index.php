<?php

try {
    echo "Тестирование интерфейса к Bill Manager\n\n";
    $die = "ТЕСТИРОВАНИЕ ЗАВЕРШИЛОСЬ С ОШИБКОЙ";

    define('LH_LIB_ROOT', '/Users/user/MyData/phplib');
    define('__LIB_ROOT__', '/Users/user/MyData/phplib/');
    require_once __DIR__ . '/autoloader.php';
    require_once LH_LIB_ROOT . '/lhValidator/classes/lhPhoneValidator.php';
    require_once __DIR__ . '/lhBillmgrInterface/classes/lhUser.php';
    require_once __DIR__ . '/lhBillmgrInterface/classes/lhTicket.php';
    require_once '/Users/user/MyData/2AC9~1/PHP/billmgr-production/lib/lh/php/classes/lhWebApi.php';
    require_once 'secrets.php';

    // Подключение к БД
    global $lhwebapi;

    // Подключение к api
    echo "Подключение к WebApi панели...";
    $lhwebapi = new lhWebApi($api_connection, $api_user, $api_pass);
    $json = json_decode($lhwebapi->apiCall('ihttpd'));
    if (is_a($json, 'stdClass')) {
        echo "ok\n";
    } else {
        echo "FAIL\n";
        die($die);
    }

    // Тестирование lhUser
    echo "Тестирование lhUser"; 

    // Массив аргументов для тестирования:
    $test_arg = [
        [lhUser::PHONE, '+7903 795-70-92', lhUser::LEVEL_USER, 'result' => 166],
        [lhUser::PHONE, '+7903 795-70-92', lhUser::LEVEL_AGENT, 'result' => NULL],
        [lhUser::ID, 80, NULL, 'result' => 80],
        [lhUser::EMAIL, 'boss@o3000.ru', lhUser::LEVEL_USER, 'result' => 68],
        [lhUser::ID, 96, lhUser::LEVEL_DEPT, 'result' => 96] 
    ];

    foreach ($test_arg as $test) {
        try {
            $user = new lhUser($test[0], $test[1], $test[2]);
            $result = $user->id();
        } catch (Exception $e) {
            if ($e->getCode() != -1) {
                throw $e;
            }
            $result = NULL;
        }
        if ($result == $test['result']) {
            echo '.';
        } else {
            echo "FAIL - result='$result'\n";
            die($die);
        }
    }
    echo "ok\n";


    // Тестирование lhTicket
    echo "\nНачало тестирования lhTicket:\n";
    echo "ID тестового пользователя: ";
    $test_user = new lhUser(lhUser::ID, 68);
    echo $test_user->id()."\n";

    echo "Есть ли активные запросы: ";
    try {
        $ticket = new lhTicket(lhTicket::ACTIVE, $test_user);
        echo "да (id: ".$ticket->id().")\n";
        echo "Для тестирования необходимо отсутствие активных тикетов у тестового пользователя!\n";
        die($die);
    }
    catch (Exception $e) {
        if ($e->getCode() != -1) {
            throw $e;
        } else {
            echo "нет (ok)\n";
        }
    }

    echo "Создание нового запроса через ACTIVE_OR_NEW... id: ";
    $ticket1 = new lhTicket(lhTicket::ACTIVE_OR_NEW, $test_user, "Проверка создания запроса через lhTicket", "Это тестовый запрос.\nОн должен удалиться сам во время тестирования");
    echo $ticket1->id()."\n";

    echo "Ожидание 2 сек"; sleep(1); echo "."; sleep(1); echo ".\n";

    echo "Создание еще одного запроса через CREATE... id: ";
    $ticket2 = new lhTicket(lhTicket::CREATE, $test_user, "Еще один тестовый запрос через lhTicket", "Это тестовый запрос.\nОн нужен для проверки выбора активного запроса из нескольких");
    echo $ticket2->id()."\n";

    echo "Поиск активного запроса когда он есть (ACTIVE_OR_NEW)...";
    $ticket_check = new lhTicket(lhTicket::ACTIVE_OR_NEW, $test_user, "Ошибка", "Если этот запрос появился - это ошибка");
    if ($ticket2->id() == $ticket_check->id()) {
        echo "ok\n";
    } else {
        echo "FAIL (Найден запрос".$ticket_check->id().", ожидается ".$ticket2->id().")\n";
        die($die);
    }

    echo "Смена активного запроса с помощью сообщения пользователя...";
    $ticket1->addMessage($test_user, (new lhSimpleMessage())->setText("Новое сообщение в запрос должно сделать его активным"));
    $ticket_check = new lhTicket(lhTicket::ACTIVE_OR_NEW, $test_user, "Ошибка", "Если этот запрос появился - это ошибка");
    if ($ticket1->id() == $ticket_check->id()) {
        echo "ok\n";
    } else {
        echo "FAIL (Найден запрос".$ticket_check->id().", ожидается ".$ticket1->id().")\n";
        die($die);
    }
    
    echo "ID тестового агента: ";
    $agent = new lhUser(lhUser::ID, 228, lhUser::LEVEL_AGENT);
    if ($agent->id() != 228) {
        throw new Exception("Ошибка создания тестового агента. Полученный ID: ".$agent->id());
    }
    echo $agent->id()."\n";
    
    echo "Добавление сообщений от агента";
    $ticket2->addMessage($agent, (new lhSimpleMessage())->setText("Тестовое сообщение от агента"));
    echo '.';
    $ticket2->addNote($agent, "Тестовая заметка от агента");
    echo ".ok\n";
    
    echo "Закрытие тестового запроса с сообщениями агента..";
    $ticket2->setResume("Это тестовый запрос. Он не требует действий.");
    echo "..ok\n";
    
    
    echo "Удаление тестового запроса.";
    $ticket1->delete();
    echo "..ok\n";
    
} catch (Exception $e) {
    echo "\n\n";
    echo $e->getMessage();
    echo "\n";
    echo $e->getTraceAsString();
    echo "\nТЕСТИРОВАНИЕ ЗАВЕРШЕНО С ОШИБКОЙ\n";
    if (isset($ticket1)) {
        echo "Удаление тествого тикета " . $ticket1->id() . '...';
        $ticket1->delete();
        echo "ok\n";
    }
    if (isset($ticket2)) {
        echo "Удаление тествого тикета " . $ticket2->id() . '...';
        $ticket2->delete();
        echo "ok\n";
    }
}
