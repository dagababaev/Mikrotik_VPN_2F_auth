# Mikrotik_VPN_2F_auth
2 factor authentification via SMS (over GSM modem) when users, workers or any equipment initilise VPN connection to MikroTik

Скрипт двухфакторной аутентификации пользователей VPN через SMS для MikroTik

- Простые скрипты ROS не создают никакой нагрузки и работают даже на hAP Lite
- Масштабируемость – возможность подключения большого количества VPN-шлюзов с целью снижения нагрузки или географического распределения
- Возможность использования Mikrotik CHR в качестве VPN-сервера
- «1хN» – 1 SMS-шлюз на неограниченное количество роутеров с возможностью расширения при росте нагрузки
- Возможность привязки отдельного роутера к «конкретному» модему
- Использование всего одного php скрипта на удаленном сервере
- Не важно какое устройство инициировало VPN-соединение, авторизация по ссылке из SMS
- Возможность вести log авторизаций сотрудников на сервере
- Увеличить отказоустойчивость и снижение нагрузки системы путем отправки SMS рандомно с нескольких модемов

------------

Two-factor authentication script for VPN users via SMS for MikroTik

- Simple ROS scripts do not create any load and even work on hAP Lite
- Scalability - the ability to connect a large number of VPN gateways to reduce load or geographical distribution
- Ability to use Mikrotik CHR as a VPN server
- “1xN” - 1 SMS gateway for an unlimited number of routers with the possibility of expansion with increasing load
- Ability to bind a separate router to a "specific" modem
- Using just one php script on a remote server
- It doesn’t matter which device initiated the VPN connection, authorization by the link from SMS
- Ability to log employee authorizations on the server
- Increase fault tolerance and reduce system load by sending SMS randomly from multiple modems
