# Configuration Docs

## Configuration location

Configuration is stored in one of two places:

- Database: This applies to all pollers and can be set with either
`lnms config:set <setting> <value>` or in the Web UI. Database config
takes precedence over `config.php` and is the favoured option.

- `config.php`: This applies to the local poller only. Configs set here
will disable in the Web UI to prevent unexpected behaviour.

## Configuration format

For configuration stored within the database, LibreNMS uses dot notation for config
items. For `config.php` this is stored as a php array under `$config`, let's
use some snmp configuration as an example:

=== "Database"
    `snmp.community`

    `snmp.community.+`

    `snmp.v3.0.authalgo`

=== "config.php"
    `$config['snmp']['community']`

    `$config['snmp']['community'][]`

    `$config['snmp']['v3'][0]['authalgo']`

!!! note
    Not all documentation has been updated to reflect using `lnms config:set` to
    set configuration items, but it will work and is the preferred option over `config.php`.

    Not all configuration settings have been defined in LibreNMS, they can still be 
    set with the `--ignore-checks` option. Without that option input is checked for 
    validity, please be careful of inputting bad values when using `--ignore-checks`. 

    Please report missing settings.

## CLI
`lnms config:get <setting>` will fetch the current config settings (composite of database, config.php, and defaults).  
`lnms config:set <setting> <value>` will set the config setting in the database.
Calling `lnms config:set <setting>` on a setting with no value will prompt you to reset
it to it's default.

If you set up bash completion, you can use tab completion to find config settings.

### Getting a list of all current values

To get a complete list of all the current values, you can use the command `lnms config:get --dump`.
To improve the readability of the output you can use the `jq` package to pretty print it:
`lnms config:get --dump | jq`.

Example output:

```bash
lnms config:get --dump | jq 
{
  "install_dir": "/opt/librenms",
  "active_directory": {
    "users_purge": 0
  },
  "addhost_alwayscheckip": false,
  "alert": {
    "ack_until_clear": false,
    "admins": true,
    "default_copy": true,
    "default_if_none": false,
    "default_mail": false,
    "default_only": true,
    "disable": false,
    "fixed-contacts": true,
    "globals": true,
    "syscontact": true,
    "transports": {
      "mail": 5
    },
    "tolerance_window": 5,
    "users": false,
    ...
```

### Examples

Below are some examples to get you started:

```bash
lnms config:get snmp.community
  [
      "public"
  ]

lnms config:set snmp.community.+ testing

lnms config:get snmp.community
  [
      "public",
      "testing"
  ]


lnms config:set snmp.community.0 private

lnms config:get snmp.community
  [
      "private",
      "testing"
  ]

lnms config:set snmp.community test
  Invalid format

lnms config:set snmp.community '["test", "othercommunity"]'

lnms config:get snmp.community
  [
      "test",
      "othercommunity"
  ]

lnms config:set snmp.community

  Reset snmp.community to the default? (yes/no) [no]:
  > yes


lnms config:get snmp.community
  [
      "public"
  ]
```

Multi-line configuration items above can be collapsed in to a single line using `| jq -c` to assist with set commands, for example:

```bash
lnms config:get snmp.community | jq -c
["public","testing"]
```

Alternatively, if leaving multi-line items exactly as returned by `lnms config:get` for easier reading, you can use the following format:
```bash
lnms config:set snmp.community \
'
[
    "public",
    "testing"
]
'
```

## Pre-load configuration

This feature is primarily for docker images and other automation.
When installing LibreNMS for the first time with a new database you can place yaml key value files
in `database/seeders/config` to pre-populate the config database.

Example snmp.yaml:

```yaml
snmp.community:
    - public
    - private
snmp.max_repeaters: 30
```

!!! danger
    The above example uses the correct, flattened notation whereas you might be tempted to create a
    block for `snmp` with sub-keys `community` and `max_repeaters`.  Do **NOT** do this as the whole `snmp`
    block will be overwritten, replaced with only those two sub-keys.  The config keys in your `seeders` file
    must match those specified in `resources/definitions/config_definitions.json`.

## Directories

```bash
lnms config:set temp_dir /tmp
```

The temporary directory is where images and other temporary files are
created on your filesystem.

```bash
lnms config:set log_dir /opt/librenms/logs
```

Log files created by LibreNMS will be stored within this directory.

## Database config

Set these variables either in .env (/opt/librenms/.env by default) or in the environment.

```dotenv
DB_HOST=127.0.0.1
DB_DATABASE=librenms
DB_USERNAME=DBUSER
DB_PASSWORD="DBPASS"
```

Use non-standard port:

```dotenv
DB_PORT=3306
```

Use a unix socket:

```dotenv
DB_SOCKET=/run/mysqld/mysqld.sock
```

## Core

### PHP Settings

You can change the memory limits for php within LibreNMS. The
value is in Megabytes and should just be an int value:

`lnms config:set php_memory_limit 128`

### Programs

A lot of these are self explanatory so no further information may be
provided. Any extensions that have dedicated documentation page will
be linked to rather than having the config provided.

#### RRDTool

You can configure these options within the WebUI now:

!!! setting "external/binaries"
    ```bash
    lnms config:set rrdtool /usr/bin/rrdtool
    ```

Please see [1 Minute polling](1-Minute-Polling.md) for information on
configuring your install to record data more frequently.

#### fping

!!! setting "external/binaries"
    ```bash
    lnms config:set fping /usr/bin/fping
    lnms config:set fping6 fping6
    ```

!!! setting "poller/ping"
    ```bash
    lnms config:set fping_options.timeout 500
    lnms config:set fping_options.count 3
    lnms config:set fping_options.interval 500
    lnms config:set fping_options.tos 184
    ```

`fping` configuration options:

* `timeout` (`fping` parameter `-t`): Amount of time that fping waits
  for a response to its first request (in milliseconds). **See note
  below**
* `count` (`fping` parameter `-c`): Number of request packets to send
  to each target.
* `interval` (`fping` parameter `-p`): Time in milliseconds that fping
  waits between successive packets to an individual target.
* `tos` (`fping`parameter `-O`): Set the type of service flag (TOS). Value can be either decimal or hexadecimal (0xh) format. Can be used to ensure that ping packets are queued in following QOS mecanisms in the network. Table is accessible in the [TOS Wikipedia page](https://en.wikipedia.org/wiki/Type_of_service).

!!! note
    Setting a higher timeout value than the interval value can
    lead to slowing down poller. Example:

    timeout: 3000

    count: 3

    interval: 500

    In this example, interval will be overwritten by the timeout value
    of 3000 which is 3 seconds. As we send three icmp packets (count:
    3), each one is delayed by 3 seconds which will result in fping
    taking > 6 seconds to return results.

You can disable the fping / icmp check that is done for a device to be
determined to be up on a global or per device basis. **We don't advise
disabling the fping / icmp check unless you know the impact, at worst
if you have a large number of devices down then it's possible that the
poller would no longer complete in 5 minutes due to waiting for snmp
to timeout.**

Globally disable fping / icmp check:

!!! setting "poller/ping"
    ```bash
    lnms config:set icmp_check false
    ```

If you would like to do this on a per device basis then you can do so
under Device -> Edit -> Misc -> Disable ICMP Test? On

#### SNMP

SNMP program locations.

!!! setting "external/binaries"
    ```bash
    lnms config:set snmpwalk /usr/bin/snmpwalk
    lnms config:set snmpget /usr/bin/snmpget
    lnms config:set snmpbulkwalk /usr/bin/snmpbulkwalk
    lnms config:set snmpgetnext /usr/bin/snmpgetnext
    lnms config:set snmptranslate /usr/bin/snmptranslate
    ```

#### Misc binaries
!!! setting "external/binaries"
    ```bash
    lnms config:set whois /usr/bin/whois
    lnms config:set ping /bin/ping
    lnms config:set mtr /usr/bin/mtr
    lnms config:set nmap /usr/bin/nmap
    lnms config:set nagios_plugins /usr/lib/nagios/plugins
    lnms config:set ipmitool /usr/bin/ipmitool
    lnms config:set virsh /usr/bin/virsh
    lnms config:set dot /usr/bin/dot
    lnms config:set sfdp /usr/bin/sfdp
    ```

## Authentication

Generic Authentication settings.

Password minimum length for auth that allows user creation

!!! setting "auth/general"
    ```bash
    lnms config:set password.min_length 8
    ```

## Proxy support

For alerting and the callback functionality, we support the use of a
http proxy setting. These can be any one of the following:

!!! setting "system/proxy"
    ```bash
    lnms config:set callback_proxy proxy.domain.com
    lnms config:set http_proxy proxy.domain.com
    ```

We can also make use of one of these environment variables which can be set in `/etc/environment`:

```bash
http_proxy=proxy.domain.com
https_proxy=proxy.domain.com
```

## RRDCached

Please refer to [RRDCached](../Extensions/RRDCached.md)

## WebUI Settings

!!! setting "system/server"
    ```bash
    lnms config:set base_url http://demo.librenms.org
    ```

LibreNMS will attempt to detect the URL you are using but you can override that here.

!!! setting "webui/style"
    ```bash
    lnms config:set site_style light
    ```

Currently we have a number of styles which can be set which will alter
the navigation bar look. device, blue, dark, light and mono with light being the default.

You can override a large number of visual elements by creating your
own css stylesheet and referencing it here, place any custom css files
into  `html/css/custom` so they will be ignored by auto updates. You
can specify as many css files as you like, the order they are within
your config will be the order they are loaded in the browser.

!!! setting "webui/style"
    ```bash
    lnms config:set webui.custom_css.+ css/custom/styles.css
    ```

You can override the default logo with yours, place any custom images
files into `html/images/custom` so they will be ignored by auto updates.

!!! setting "webui/style"
    ```bash
    lnms config:set title_image images/custom/yourlogo.png
    ```

Set how often pages are refreshed in seconds. The default is every 5
minutes. Some pages don't refresh at all by design.

!!! setting "webui/general"
    ```bash
    lnms config:set page_refresh 300
    ```

You can create your own front page by adding a blade file in `resources/views/overview/custom/`
and setting `front_page` to it's name.
For example, if you create `resources/views/overview/custom/foobar.blade.php`, set `front_page` to `foobar`.

!!! setting "webui/front-page"
```bash
lnms config:set front_page default
```

Set a global default dashboard page for any user who has not set one in their user
preferences.  Should be set to dashboard_id of an existing dashboard that is Shared,
Shared(read) or Shared (Admin RW). Otherwise, the system will automatically create
each user an empty dashboard called `Default` on their first login.

!!! setting "webui/dashboard"
    ```bash
    lnms config:set webui.default_dashboard_id 0
    ```

This is the default message on the login page displayed to users.

!!! setting "auth/general"
    ```bash
    lnms config:set login_message "Unauthorised access or use shall render the user liable to criminal and/or civil prosecution."
    ```

If this is set to true then an overview will be shown on the login page of devices and the status.

!!! setting "auth/general"
    ```bash
    lnms config:set public_status true
    ```

Enable / disable certain menus from being shown in the WebUI.

!!! setting "webui/menu"
    ```bash
    lnms config:set show_locations true  # Enable Locations on menu
    lnms config:set show_locations_dropdown true  # Enable Locations dropdown on menu
    lnms config:set show_services false  # Disable Services on menu
    lnms config:set int_customers true  # Enable Customer Port Parsing
    lnms config:set int_transit true  # Enable Transit Types
    lnms config:set int_peering true  # Enable Peering Types
    lnms config:set int_core true  # Enable Core Port Types
    lnms config:set int_l2tp false  # Disable L2TP Port Types
    ```

!!! setting "webui/dashboard"
    ```bash
    lnms config:set summary_errors false  # Show Errored ports in summary boxes on the dashboard
    ```

!!! setting "webui/port-descr"
    lnms config:set customers_descr '["cust"]'  # The description to look for in ifDescr. Can have multiple '["cust","cid"]'
    lnms config:set transit_descr '["transit"]'  # Add custom transit descriptions (array)
    lnms config:set peering_descr '["peering"]'  # Add custom peering descriptions (array)
    lnms config:set core_descr '["core"]'  # Add custom core descriptions  (array)
    lnms config:set custom_descr '["This is Custom"]'  # Add custom interface descriptions (array)
    ```

You are able to adjust the number and time frames of the quick select
time options for graphs and the mini graphs shown per row.

Quick select:

```bash
lnms config:set graphs.mini.normal '{
    "day": "24 Hours",
    "week": "One Week",
    "month": "One Month",
    "year": "One Year"
}'

lnms config:set graphs.mini.widescreen '{
    "sixhour": "6 Hours",
    "day": "24 Hours",
    "twoday": "48 Hours",
    "week": "One Week",
    "twoweek": "Two Weeks",
    "month": "One Month",
    "twomonth": "Two Months",
    "year": "One Year",
    "twoyear": "Two Years"
}'
```

Mini graphs:

```bash
lnms config:set graphs.row.normal '{
    "sixhour": "6 Hours",
    "day": "24 Hours",
    "twoday": "48 Hours",
    "week": "One Week",
    "twoweek": "Two Weeks",
    "month": "One Month",
    "twomonth": "Two Months",
    "year": "One Year",
    "twoyear": "Two Years"
}'
```

You can disable the mouseover popover for mini graphs by setting this to false.

!!! setting "webui/general"
    ```bash
    lnms config:set web_mouseover true
    ```

You can disable image lazy loading by setting this to false.

!!! setting "webui/general"
    ```bash
    lnms config:set enable_lazy_load true
    ```

Enable or disable the sysDescr output for a device.

!!! setting "webui/general"
    ```bash
    lnms config:set overview_show_sysDescr true
    ```

This is a simple template to control the display of device names by default.
You can override this setting per-device by editing the device within the WebUI.

You may enter any free-form text including one or more of the following template replacements:

| Template                    | Replacement                                                          |
|-----------------------------|----------------------------------------------------------------------|
| `{{ $hostname }}`           | The hostname or IP of the device that was set when added  *default   |
| `{{ $sysName_fallback }}`   | The hostname or sysName if hostname is an IP                         |
| `{{ $sysName }}`            | The SNMP sysName of the device, falls back to hostname/IP if missing |
| `{{ $ip }}`                 | The actual polled IP of the device, will not display a hostname      |

For example, `{{ $sysName_fallback }} ({{ $ip }})` will display something like `server (192.168.1.1)`

!!! setting "webui/device"
    ```bash
    lnms config:set device_display_default '{{ $hostname }}'
    ```

Interface types that aren't show in graphs in the WebUI. The default array
contains more items, please see resources/definitions/config_definitions.json for the full list.

!!! setting "webui/graph"
    ```bash
    lnms config:set device_traffic_iftype.+ '/loopback/'
    ```

Administrators are able to clear the last discovered time of a device
which will force a full discovery run within the configured time window.

!!! setting "webui/device"
    ```bash
    lnms config:set enable_clear_discovery true
    ```

Disable the footer of the WebUI by setting `enable_footer` to 0.

!!! setting "webui/general"
    ```bash
    lnms config:set enable_footer true
    ```

Show the `X`th percentile in the graph instead of the default 95th percentile.

!!! setting "webui/graph"
    ```bash
    lnms config:set percentile_value 90
    ```

The target maximum hostname length when applying the shorthost() function.
You can increase this if you want to try and fit more of the hostname in graph titles.
The default value is 12. However, this can possibly break graph
generation if this is very long.

!!! setting "webui/graph"
    ```bash
    lnms config:set shorthost_target_length 15
    ```

You can enable dynamic graphs which allow you to zoom in/out and scroll through
the timeline of the graphs quite easiy.

!!! setting "webui/graph"
    ```bash
    lnms config:set webui.dynamic_graphs true
    ```

Graphs will be movable/scalable without reloading the page:
![Example dynamic graph usage](img/dynamic-graph-usage.gif)

## Stacked Graphs

You can enable stacked graphs instead of the default inverted
graphs.

!!! setting "webui/graph"
    ```bash
    lnms config:set webui.graph_stacked true
    ```

## Add host settings

The following setting controls how hosts are added.  If a host is
added as an ip address it is checked to ensure the ip is not already
present. If the ip is present the host is not added. If host is added
by hostname this check is not performed.  If the setting is true
hostnames are resolved and the check is also performed.  This helps
prevents accidental duplicate hosts.

!!! setting "discovery/general"
    ```bash
    lnms config:set addhost_alwayscheckip false # true - check for duplicate ips even when adding host by name.
                                                # false- only check when adding host by ip.
    ```

By default we allow hosts to be added with duplicate sysName's, you
can disable this with the following config:

!!! setting "discovery/general"
```bash
lnms config:set allow_duplicate_sysName false
```

## Global poller and discovery modules

Enable or disable discovery or poller modules.

This setting has an order of precedence. Device settings override
per OS settings which override Global settings. (Device -> OS -> Global).

So if the module is set at a more specific level, it will override the
less specific settings.

Global:

!!! setting "discovery/discovery_modules"
    ```bash
    lnms config:set discovery_modules.arp-table false
    lnms config:set discovery_modules.entity-state true
    ```

!!! setting "poller/poller_modules"
    ```bash
    lnms config:set poller_modules.entity-state true
    ```

Per OS:

```bash
lnms config:set os.ios.discovery_modules.arp-table false
lnms config:set os.ios.discovery_modules.entity-state true

lnms config:set os.ios.poller_modules.entity-state true
```

## SNMP Settings

Default SNMP options including retry and timeout settings and also
default version and port.

!!! setting "poller/snmp"
    ```bash
    lnms config:set snmp.timeout 1                         # timeout in seconds
    lnms config:set snmp.retries 5                         # how many times to retry the query
    lnms config:set snmp.transports '["udp", "udp6", "tcp", "tcp6"]'    # Transports to use
    lnms config:set snmp.version '["v2c", "v3", "v1"]'       # Default versions to use
    lnms config:set snmp.port 161                          # Default port
    lnms config:set snmp.exec_timeout 1200                 # execution time limit in seconds
    ```

> NOTE: `timeout` is the time to wait for an answer and `exec_timeout`
> is the max time to run a query.

The default v1/v2c snmp community to use, you can expand this array
with `[1]`, `[2]`, `[3]`, etc.

!!! setting "poller/snmp"
    ```bash
    lnms config:set snmp.community.0 public
    ```

!!! note
    This list of SNMP communities is used for auto discovery if enabled,
    and as a default set for any manually added device.

The default v3 snmp details to use, you can expand this array with
`[1]`, `[2]`, `[3]`, etc.

!!! setting "poller/snmp"
    ```bash
    lnms config:set snmp.v3.0 '{
        authlevel: "noAuthNoPriv",
        authname: "root",
        authpass: "",
        authalgo: "MD5",
        cryptopass: "",
        cryptoalgo: "AES"
    }'
    ```

```
authlevel   noAuthNoPriv | authNoPriv | authPriv
authname    User Name (required even for noAuthNoPriv)
authpass    Auth Passphrase
authalgo    MD5 | SHA | SHA-224 | SHA-256 | SHA-384 | SHA-512
cryptopass  Privacy (Encryption) Passphrase
cryptoalgo  AES | AES-192 | AES-256 | AES-256-C | DES
```

## Auto discovery settings

Please refer to [Auto-Discovery](../Extensions/Auto-Discovery.md)

## Email configuration

!!! setting "alerting/email"
    ```bash
    lnms config:set email_backend mail
    lnms config:set email_from librenms@yourdomain.local
    lnms config:set email_user `lnms config:get project_id`
    lnms config:set email_sendmail_path /usr/sbin/sendmail
    lnms config:set email_smtp_host localhost
    lnms config:set email_smtp_port 25
    lnms config:set email_smtp_timeout 10
    lnms config:set email_smtp_secure tls
    lnms config:set email_smtp_auth false
    lnms config:set email_smtp_username NULL
    lnms config:set email_smtp_password NULL
    ```

What type of mail transport to use for delivering emails. Valid
options for `email_backend` are mail, sendmail or smtp. The varying
options after that are to support the different transports.

For security reasons, the SMTP server connection via TLS will try to verify the validity of the certificate. If for some reason you need to disable verification, you can use the email_smtp_verifypeer option (true by default) and email_smtp_allowselfsigned (false by default).

!!! setting "alerting/email"
    ```bash
        lnms config:set email_smtp_verifypeer false
        lnms config:set email_smtp_allowselfsigned true
    ```

## Alerting

Please refer to [Alerting](../Alerting/index.md)

## Billing

Please refer to [Billing](../Extensions/Billing-Module.md)

## Global module support

!!! setting "webui/menu"
    ```bash
    lnms config:set enable_syslog false # Enable Syslog
    lnms config:set enable_inventory true # Enable Inventory
    lnms config:set enable_pseudowires true # Enable Pseudowires
    ```

```bash
lnms config:set enable_vrfs true # Enable VRFs
```

## Port extensions

Please refer to [Port-Description-Parser](../Extensions/Interface-Description-Parsing.md)

Enable / disable additional port statistics.

```bash
lnms config:set enable_ports_etherlike false
lnms config:set enable_ports_junoseatmvp false
lnms config:set enable_ports_poe false
```

## Port Group

Assign a new discovered Port automatically to Port Group with this Port Group ID
(0 means no Port Group assignment)

!!! setting "discovery/ports"
    ```bash
    lnms config:set default_port_group 0
    ```

## External integration

### Rancid

Rancid configuration, `rancid_configs` is an array containing all of
the locations of your rancid files. Setting `rancid_ignorecomments`
will disable showing lines that start with #

!!! setting "external/rancid"
    ```bash
    lnms config:set rancid_configs.+ /var/lib/rancid/network/configs/
    lnms config:set rancid_repo_type svn
    lnms config:set rancid_ignorecomments false
    ```

### Oxidized

Please refer to [Oxidized](../Extensions/Oxidized.md)

### CollectD

Specify the location of the collectd rrd files. Note that the location
in LibreNMS should be consistent with the location set in
/etc/collectd.conf and etc/collectd.d/rrdtool.conf

!!! setting "external/collectd"
    ```bash
    lnms config:set collectd_dir /var/lib/collectd/rrd
    ```

`/etc/collectd.conf`
```bash
<Plugin rrdtool>
        DataDir "/var/lib/collectd/rrd"
        CreateFilesAsync false
        CacheTimeout 120
        CacheFlush   900
        WritesPerSecond 50
</Plugin>
```

`/etc/collectd.d/rrdtool.conf`
```bash
LoadPlugin rrdtool
<Plugin rrdtool>
       DataDir "/var/lib/collectd/rrd"
       CacheTimeout 120
       CacheFlush   900
</Plugin>
```

Specify the location of the collectd unix socket. Using a socket
allows the collectd graphs to be flushed to disk before being
drawn. Be sure that your web server has permissions to write to this socket.

!!! setting "external/collectd"
    ```bash
    lnms config:set collectd_sock unix:///var/run/collectd.sock
    ```

### Smokeping

Please refer to [Smokeping](../Extensions/Smokeping.md)

### NFSen

Please refer to [NFSen](../Extensions/NFSen.md)

### Location parsing

LibreNMS can interpret sysLocation information and map the device loction based on GeoCoordinates or GeoCoding information.

- Info-keywords
  - `[]` contains optional Latitude and Longitude information if manual GeoCoordinate positioning is desired.
  - `()` contains optional information that is ignored during GeoCoding lookups.


#### GeoCoordinates

If device sysLocation information contains [lat, lng] (note the comma and square brackets), that is used to determin the GeoCoordinates.

Example:
```bash
name_that_can_not_be_looked_up [40.424521, -86.912755]
```

The coordinates will then be set to 40.424521 latitude and -86.912755 longitude.

#### GeoCoding

Next it will attempt to look up the sysLocation with a map engine provided you have configured one under
`lnms config:get geoloc.engine`. The information has to be accurate or no result is returned, when it
does it will ignore any information inside parentheses, allowing you to add details that would otherwise
interfeeer with the lookup.

Example:
```bash
1100 Congress Ave, Austin, TX 78701 (3rd floor)
Geocoding lookup is:
1100 Congress Ave, Austin, TX 78701
```

#### Overrides

1. You can overwrite a devices sysLocation in the WebGui     under "Device settings" for that device.
2. You can set the location coordinates for a location in the WebGui under Device > Geo Locations -> All Location.

### Location mapping

If you just want to set GPS coordinates on a location, you should
visit Devices > Geo Locations > All Locations and edit the coordinates
there.

However you can replace the sysLocation value that is returned for a single device or many devices.

For example, let's say that you have 100 devices which all contain the sysLocation value of `Under the Sink` which
isn't the real address, rather than editing each device manually, you can specify a mapping to override the sysLocation
value.

Exact Matching:

`Under the Sink` Will become `Under The Sink, The Office, London, UK`

!!! setting "webui/device"
    ```bash
    lnms config:set location_map '{"Under the Sink": "Under The Sink, The Office, London, UK"}'
    ```

Regex Matching:

`Not Under the Sink` Will become `Not Under The Sink, The Office, London, UK`

!!! setting "webui/device"
    ```bash
    lnms config:set location_map_regex '{"/Sink/": "Not Under The Sink, The Office, London, UK"}'
    ```

Regex Match Substitution:

`Rack10,Rm-314,Sink` Will become `Rack10,Rm-314,Under The Sink, The Office, London, UK [lat, lng]`

!!! setting "webui/device"
    ```bash
    lnms config:set location_map_regex_sub '{"/Sink/": "Under The Sink, The Office, London, UK [lat, long]"}'
    ```

The above are examples, these will rewrite device snmp locations so you don't need
to configure full location within snmp.

## Interfaces to be ignored

Interfaces can be automatically ignored during discovery by modifying
various configuration options, unsetting default options and customizing
it, or creating an OS specific option. The preferred method for ignoring
interfaces is to use an OS specific option. The default options can be
found in resources/definitions/config_definitions.json. Default OS specific
definitions can be found in `resources/definitions/os_detection/\_specific_os_.yaml`
and can contain bad_if\* options, but should only be modified via pull-request as
manipulation of the definition files will block updating:

Examples:

#### Add entries to default option

!!! setting "discovery/ports"
    ```bash
    lnms config:set bad_if.+ voip-null
    lnms config:set bad_iftype.+ voiceEncap
    lnms config:set bad_if_regexp.+ '/^lo[0-9].*/'    # loopback
    ```

#### Override default bad_if values

!!! setting "discovery/ports"
    ```bash
    lnms config:set bad_if '["voip-null", "voiceEncap", "voiceFXO"]'
    ```

#### Create an OS specific array

!!! setting "discovery/ports"
    ```bash
    lnms config:set os.iosxe.bad_iftype.+ macSecControlledIF
    lnms config:set os.iosxe.bad_iftype.+ macSecUncontrolledIF
    ```

#### Various bad_if\* selection options available

`bad_if` is matched against the ifDescr value.

`bad_iftype` is matched against the ifType value.

`bad_if_regexp` is matched against the ifDescr value as a regular expression.

`bad_ifname_regexp` is matched against the ifName value as a regular expression.

`bad_ifalias_regexp` is matched against the ifAlias value as a regular expression.

## Interfaces that shouldn't be ignored

It's also possible to whitelist ports so they are not ignored. `good_if` can
be configured both globally and per os just like `bad_if`.

As an examples, let's say we have `bad_if_regexp` set to ignore `Ethernet` ports
but realise that we actually still want `FastEthernet` ports but not any others,
we can add a `good_if` option to white list `FastEthernet`:

!!! setting "discovery/ports"
    ```bash
    lnms config:set good_if.+ FastEthernet
    lnms config:set os.ios.good_if.+ FastEthernet
    ```

`good_if` is matched against ifDescr value. This can be a bad_if value
as well which would stop that port from being ignored. i.e. if bad_if
and good_if both contained FastEthernet then ports with this value in
the ifDescr will be valid.

## Interfaces to be rewritten

You can rewrite the interface label automatically using the following
options.

Entries defined in `rewrite_if` are being replaced completely.
Entries defined in `rewrite_if_regexp` only replace the match.
Matches are compared case-insensitive.

!!! setting "discovery/ports"
    ```bash
    lnms config:set rewrite_if '{"cpu": "Management Interface"}'
    lnms config:set rewrite_if_regexp '{"/cpu /": "Management "}'
    ```

## Entity sensors to be ignored

Some devices register bogus sensors as they are returned via SNMP but
either don't exist or just don't return data. This allows you to
ignore those based on the descr field in the database. You can either
ignore globally or on a per os basis (recommended).

As an example, if you have some sensors which contain the descriptions
below:

```text
Physical id 1
Physical id 2
...
Physical id 4
```

!!! setting "discovery/sensors"
    ```bash
    lnms config:set bad_entity_sensor_regex.+ '/Physical id [0-9]+/'
    lnms config:set os.ios.bad_entity_sensor_regex '["/Physical id [0-9]+/"]'
    ```

## Entity sensors limit values

Vendors may give some limit values (or thresholds) for the discovered
sensors. By default, when no such value is given or LibreNMS doesn't have,
support for those limits, both high and low limit values are guessed,
based on the value measured during the initial discovery.

When it is preferred to have no high and/or low limit values at all if
these are not provided by the vendor, the guess method can be disabled:

!!! settings "discovery/sensors"
    ```bash
    lnms config:set sensors.guess_limits false
    ```

## Ignoring Health Sensors

It is possible to filter some sensors from the configuration:

### Ignore all temperature sensors

!!! settings "discovery/sensors"
    ```bash
    lnms config:set disabled_sensors.temperature true
    ```

### Filter all sensors matching regexp ```'/PEM Iout/'```.

!!! settings "discovery/sensors"
    ```bash
    lnms config:set disabled_sensors_regex.+ '/PEM Iout/'
    ```

### Filter all 'current' sensors for Operating System 'vrp'.

```bash
lnms config:set os.vrp.disabled_sensors.current true
```

### Filter all sensors matching regexp ```'/PEM Iout/'``` for Operating System iosxe.

```bash
lnms config:set os.iosxe.disabled_sensors_regex '/PEM Iout/'
```

## Processor configuration

Custom processor warning percentage which will be set when processor information
is discovered and the perc

!!! setting "discovery/processor"
    ```bash
    lnms config:set processor.default_perc_warn 75
    ```

## Storage configuration

Storage / mount points to ignore in discovery and polling.

!!! setting "discovery/storage"
    ```bash
    lnms config:set ignore_mount_removable true
    lnms config:set ignore_mount_network true
    lnms config:set ignore_mount_optical true

    lnms config:set ignore_mount.+ /kern
    lnms config:set ignore_mount.+ /mnt/cdrom
    lnms config:set ignore_mount.+ /proc
    lnms config:set ignore_mount.+ /dev

    lnms config:set ignore_mount_string.+ packages
    lnms config:set ignore_mount_string.+ devfs
    lnms config:set ignore_mount_string.+ procfs
    lnms config:set ignore_mount_string.+ UMA
    lnms config:set ignore_mount_string.+ MALLOC

    lnms config:set ignore_mount_regexp.+ '/on: \/packages/'
    lnms config:set ignore_mount_regexp.+ '/on: \/dev/'
    lnms config:set ignore_mount_regexp.+ '/on: \/proc/'
    lnms config:set ignore_mount_regexp.+ '/on: \/junos^/'
    lnms config:set ignore_mount_regexp.+ '/on: \/junos\/dev/'
    lnms config:set ignore_mount_regexp.+ '/on: \/jail\/dev/'
    lnms config:set ignore_mount_regexp.+ '/^(dev|proc)fs/'
    lnms config:set ignore_mount_regexp.+ '/^\/dev\/md0/'
    lnms config:set ignore_mount_regexp.+ '/^\/var\/dhcpd\/dev,/'
    lnms config:set ignore_mount_regexp.+ '/UMA/'
    ```

Custom storage warning percentage which will be set when storage information
is discovered.

!!! setting "discovery/storage"
    ```bash
    lnms config:set storage_perc_warn 60
    ```

## IRC Bot

Please refer to [IRC Bot](../Extensions/IRC-Bot.md)

## Authentication

Please refer to [Authentication](../Extensions/Authentication.md)

## Cleanup options

Please refer to [Cleanup Options](../Support/Cleanup-options.md)

## Syslog options

Please refer to [Syslog](../Extensions/Syslog.md)

## Virtualization

Enable this to switch on support for libvirt along with `libvirt_protocols`
to indicate how you connect to libvirt.  You also need to:

1. Generate a non-password-protected ssh key for use by LibreNMS, as the
    user which runs polling & discovery (usually `librenms`).
1. On each VM host you wish to monitor:
   1. Configure public key authentication from your LibreNMS server/poller by
      adding the librenms public key to `~root/.ssh/authorized_keys`.
   1. (xen+ssh only) Enable libvirtd to gather data from xend by setting
      `(xend-unix-server yes)` in `/etc/xen/xend-config.sxp` and
      restarting xend and libvirtd.

To test your setup, run `virsh -c qemu+ssh://vmhost/system list` or
`virsh -c xen+ssh://vmhost list` as your librenms polling user.

!!! setting "external/virtualization"
    ```bash
    lnms config:set enable_libvirt true
    lnms config:set libvirt_protocols '["qemu+ssh","xen+ssh"]'
    lnms config:set libvirt_username root
    ```

## BGP Support

You can use this config option to rewrite the description of ASes that you have discovered.

!!! setting "discovery/general"
    ```bash
    lnms config:set astext.65332 "Cymru FullBogon Feed"
    ```

## Auto updates

Please refer to [Updating](../General/Updating.md)

## IPMI

Setup the types of IPMI protocols to test a host for and in what
order. Don't forget to install ipmitool on the monitoring host.

!!! setting "discovery/ipmi"
    ```bash
    lnms config:set ipmi.type '["lanplus", "lan", "imb", "open"]'
    ```

## Distributed poller settings

Please refer to [Distributed Poller](../Extensions/Distributed-Poller.md)

## API Settings

## CORS Support

<https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS>

CORS support for the API is disabled by default. Below you will find
the standard options, all of which you can configure.

!!! setting "api/cors"
    ```bash
    lnms config:set api.cors.enabled false
    lnms config:set api.cors.origin '["*"]'
    lnms config:set api.cors.maxage '86400'
    lnms config:set api.cors.allowmethods '["POST", "GET", "PUT", "DELETE", "PATCH"]'
    lnms config:set api.cors.allowheaders '["Origin", "X-Requested-With", "Content-Type", "Accept", "X-Auth-Token"]'
    lnms config:set api.cors.exposeheaders '["Cache-Control", "Content-Language", "Content-Type", "Expires", "Last-Modified", "Pragma"]'
    lnms config:set api.cors.allowmethods '["POST", "GET", "PUT", "DELETE", "PATCH"]'
    lnms config:set api.cors.allowheaders '["Origin", "X-Requested-With", "Content-Type", "Accept", "X-Auth-Token"]'
    lnms config:set api.cors.exposeheaders '["Cache-Control", "Content-Language", "Content-Type", "Expires", "Last-Modified", "Pragma"]'
    lnms config:set api.cors.allowcredentials false
    ```
