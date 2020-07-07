# Need to replace with your values in this code: yourwebsite.com, ROUTERLOGIN, ROUTER_PASSWORD
# Place #phone number / #synology_chat_token / #telegram_id in user ppp secret comment


/ip firewall address-list
add address=yourwebsite.com list=VPN_allow-list
add address=8.8.8.8 list=VPN_allow-list
add address=10.200.1.1 list=VPN_allow-list
add address=telegram.org list=Allow-list
add address=149.154.172.0/22 list=Allow-list comment=telegram
add address=149.154.168.0/22 list=Allow-list comment=telegram
add address=149.154.164.0/22 list=Allow-list comment=telegram
add address=149.154.160.0/22 list=Allow-list comment=telegram
add address=91.108.56.0/22 list=Allow-list comment=telegram
add address=91.108.16.0/22 list=Allow-list comment=telegram
add address=91.108.12.0/22 list=Allow-list comment=telegram
add address=91.108.8.0/22 list=Allow-list comment=telegram
add address=91.108.4.0/22 list=Allow-list comment=telegram

/ip firewall raw
add action=drop chain=prerouting dst-address-list=!VPN_allow-list src-address-list=VPN-unauth disabled=no

/ip pool
add name=2F-VPN_pool ranges=10.200.1.10-10.200.1.254

/ppp profile
add change-tcp-mss=no dns-server=10.10.0.1 idle-timeout=29m local-address=10.200.1.1 name=2F-VPN on-down=":global ruidlogin \"ROUTERLOGIN\";\r\
    \n:global ruidpass \"ROUTER_PASSWORD\";\r\
    \n:global host \"https://yourwebsite.com/\";\r\
    \n\r\
    \n:local userip \$\"remote-address\";\r\
    \n:local username \$user;\r\
    \n\r\
    \n/tool fetch http-method=post http-data=\"ruid=\$ruidlogin&pass=\$ruidpass&username=\$username&remote-ip=\$userip&action=down\" url=\"\$host\" mode=ht\
    tps as-value output=user;\r\
    \n/ip firewall address-list remove [find address=\$userip];\r\
    \n\r\
    \n:log warning \"User disconnected:\";\r\
    \n:log warning \$user;\r\
    \n:log warning \$userip;\r\
    \n" on-up=":global ruidlogin \"ROUTERLOGIN\";\r\
    \n:global ruidpass \"ROUTER_PASSWORD\";\r\
    \n:global host \"https://yourwebsite.com/\";\r\
    \n\r\
    \n:local userip \$\"remote-address\";\r\
    \n:local userAddress [/ppp secret get [find name=\$user] comment];\r\
    \n:local username \$user;\r\
    \n\r\
    \n:local authkey [/tool fetch http-method=post http-data=\"ruid=\$ruidlogin&pass=\$ruidpass&\$userAddress&username=\$username&remote-ip=\$userip\" url=\
    \"\$host\" mode=https as-value output=user];\r\
    \n\r\
    \n/ip firewall address-list remove [find address=\$userip];\r\
    \n/ip firewall address-list add address=\$userip list=VPN-blocked timeout=30m comment=(\$authkey->\"data\");\r\
    \n\r\
    \n:log warning \"User connect:\";\r\
    \n:log warning \$username;\r\
    \n:log warning \$userip;\r\
    \n:log warning (\$authkey->\"data\");" remote-address=2F-VPN_pool use-compression=no use-encryption=yes use-mpls=no use-upnp=no

/ppp secret
add comment="phone=<user_phone_number>" name=username1 password=testuser profile=2F-VPN disabled=yes
add comment="synology=<synology_token>" name=username2 password=testuser profile=2F-VPN disabled=yes
add comment="telegram=<telegram_username_id>" name=username3 password=testuser profile=2F-VPN disabled=yes
