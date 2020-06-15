/ip firewall address-list
add address=8.8.8.8 list=VPN_allow-list
add address=yourwebsite.com list=VPN_allow-list

/ip firewall raw
add action=drop chain=prerouting dst-address-list=!VPN_allow-list src-address-list=VPN-unauth disabled=no

/ip pool
add name=2F-VPN_pool ranges=10.10.1.10-10.10.1.254

/ppp profile
add change-tcp-mss=no dns-server=8.8.8.8 idle-timeout=59m local-address=10.10.1.1 name=2F-VPN on-down=":global ruidlogin \"#ROUTERLOGIN#\";\r\
    \n:global ruidpass \"#ROUTER_PASSWORD#\";\r\
    \n:local userip $"remote-address";\r\
    \n# if phone number stored in comment\r\
    \n:local userphone [/ppp secret get [find name=\$user] comment];\r\
    \n# if phone number = username\r\
    \n#:local userphone \$user;\r\
    \n\r\
    \n/tool fetch http-method=post http-data=\"ruid=\$ruidlogin&pass=\$ruidpass&tel=\$userphone&action=down\" url=\"https://yourwebsite.com/\" mode=https as-value output=user;\r\
    \n/ip firewall address-list remove [find address=\$userip];\r\
    \n\r\
    \n:log info message=\"User disconnected:\";\r\
    \n:log info message=\$user;\r\
    \n:log info message=\$userip;" on-up=":global ruidlogin \"#ROUTERLOGIN#\";\r\
    \n:global ruidpass \"#ROUTER_PASSWORD#\";\r\
    \n:local userip $"remote-address";\r\
    \n# if phone number stored in comment\r\
    \n:local userphone [/ppp secret get [find name=\$user] comment];\r\
    \n# if phone number = username\r\
    \n#:local userphone \$user;\r\
    \n\r\
    \n:local authkey [/tool fetch http-method=post http-data=\"ruid=\$ruidlogin&pass=\$ruidpass&tel=\$userphone&remote-ip=\$userip\" url=\"https://yourwebsite.com/\" mode=https as-value output=user];\r\
    \n\r\
    \n/ip firewall address-list remove [find address=\$userip];\r\
    \n/ip firewall address-list add address=\$userip list=VPN-blocked timeout=1h comment=(\$authkey->\"data\");\r\
    \n\r\
    \n:log info message=\"User connect:\";\r\
    \n:log info message=\$userphone;\r\
    \n:log info message=\$userip;\r\
    \n:log info message=(\$authkey->\"data\");" remote-address=vpn_pool use-compression=no use-encryption=yes use-mpls=no use-upnp=no

/ppp secret
add comment=70001234567 name=70001234567 password=testuser profile=2F-VPN
