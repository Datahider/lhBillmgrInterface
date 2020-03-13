<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * регистратор нового клиента и пользователя в панели
 * 
 * Конструктор регистрирует пользователя или выбрасывает исключение
 * Коды исключений до от -1 до -100 зарезервированы и содержат сообщения,
 *   которые могут быть переданы пользователю
 * Функция user() возвращает объект lhUser зарегистрированного пользователя
 *
 * @author user
 */
interface lhUserRegistratorInterface {

    public function __construct(...$args);
    public function user();
    
}
