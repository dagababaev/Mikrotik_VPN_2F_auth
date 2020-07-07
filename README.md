# Mikrotik_VPN_2F_auth
2 factor authentification using SMS (over gsm modem or payed sms gateway) / Synology Chat / Telegram bot when users or any equipment create VPN connection to Mikrotik

Скрипт двухфакторной аутентификации пользователей VPN через SMS для MikroTik

- Простые скрипты ROS не создают никакой нагрузки и работают даже на hAP Lite
- Масштабируемость – возможность подключения большого количества VPN-шлюзов с целью снижения нагрузки или географического распределения
- Возможность использования Mikrotik CHR в качестве VPN-сервера
- «1хN» – 1 SMS-шлюз на неограниченное количество роутеров с возможностью расширения при росте нагрузки
- Возможность привязки отдельного роутера к «конкретному» модему
- Использование всего одного php скрипта на удаленном сервере
- Не важно какое устройство инициировало VPN-соединение, авторизация по ссылке из SMS
- Ведение log'а всех запросов на сервере (можно вкл/выкл)
- Увеличение отказоустойчивости и снижения нагрузки системы путем отправки SMS рандомно с нескольких модемов
- Возможность отправки SMS через платные шлюзы (на примере https://smsc.ru)
- Firewall – доступ на генерацию кодов и отправку SMS только у роутеров занесенных в список (можно вкл/выкл)
– Отправка кодов авторизации в Synology Chat и через Telegram Bot

------------

Two-factor authentication script for VPN users via SMS for MikroTik

- Simple ROS scripts do not create any load and even work on hAP Lite
- Scalability - the ability to connect a large number of VPN gateways to reduce load or geographical distribution
- Ability to use Mikrotik CHR as a VPN server
- “1xN” - 1 SMS gateway for an unlimited number of routers with the possibility of expansion with increasing load
- Ability to bind a separate router to a "specific" modem
- Using just one php script on a remote server
- It doesn’t matter which device initiated the VPN connection, authorization by the link from SMS
- Authorization log on the server (may be on/off)
- Increase fault tolerance and reduce system load by sending SMS randomly from multiple modems
- Send SMS via paid sms center getaway (for example https://smsc.ru)
- Firewall - allow access to generate codes and send SMS only for router from array (may be on/off)
– Send auth code via Synology Chat and Telegram Bot
