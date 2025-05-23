# Shooter - A Troubleshooting Tool for OpenMage Developers

A browser-centric troubleshooting tool for OpenMage developers. Features:

- Logging capabilities:
  - Custom logging with timestamp, URI, and user context
  - Support logging of objects (Varien_Object and collections)
  - Trace stack logging for debugging
- Browser debug output:
  - Recent logs from exception, system, and other log files
  - Echo variables, objects, and collections with formatting
  - Display controller request parameters including uploaded files parameters
- System information:
  - Server host name, IP address, OS, PHP version
  - OpenMage version
  - PHP information
- OAuth 1.0a Tester:
  - Test OpenMage REST API as a consumer
  - Test SSL connection

**CAUTION**: This module is designed for development environments. It may not be suitable for production environments due to the potential for exposing sensitive information.

## Table of Contents

1. [Quick Start](#quick-start)
    - [Debug Helper](#debug-helper)
    - [Output the Log Files in the Browser](#output-the-log-files-in-the-browser)
    - [Capture the Last Error](#capture-the-last-error)
    - [Show Information in the Browser](#show-information-in-the-browser)
    - [REST OAuth 1.0a Tester](#oauth-10a-tester)
2. [Configuration](#configuration)
    - [Customizing Customer IDs for Access](#customizing-customer-ids-for-access)
2. [Installation](#installation)
    - [Composer](#composer)
    - [Manual Installation](#manual-installation)
3. [Contributing](#contributing)
4. [License](#license)

## Quick Start

You need to be able to access the customers in the database. By default, the first 20 customers with ID from 1 to 20 are allowed to view the output in the browser. You can edit the `entity_id` in the table `customer_entity` for these users. To allow access for specified customer IDs, see [Configuration](#configuration).

### Debug Helper

Example uses of the helper:
```php
// Add a log entry to file var/log/shooter.log:
Mage::helper('shooter')->log("string=$string", $var); // Log a message
Mage::helper('shooter')->trace('trace message'); // Trace the call stack

// To troubleshoot controllers:
return Mage::helper('shooter')->echoParams($var); // Display the request parameters, $var is optional
return Mage::helper('shooter')->echo($var, $title); // Display a variable, $title is optional
```

### Output the Log Files in the Browser

#### URI `/shooter/log`
To output the recent entries of the log files in `var/log/*.log` and `var/report/{latest file}`:
```
{http://your_domain}/shooter/log
```

Optional parameters:
- `?lines=L`, where L is the number of lines to display, default is 80.
- `?secs=S`, where S is the number of seconds from the modification time beyond which the file will not be displayed, default is 80.

For example, to list the log files from the last 120 lines and modified in the last hour:

```
{http://your_domain}/shooter/log?lines=120&secs=3600
```

#### URI `shooter/log/tail`
To show `exception.log`:
```
{http://your_domain}/shooter/log/tail
```
Optional parameters:
- `?fnm=F`, where F is the name of the file to display, default is `exception.log`.
- `?lines=L`, where L is the number of lines to display, default is 80.
- `?dir=D`, where D is the directory of the file to display, default is `var/log`.

Refer to [LogController.php](app/code/community/Kiatng/Shooter/controllers/LogController.php) for more details.

#### Capture `error_get_last()`
To capture the last error, insert the following code in index.php, just before the last line `Mage::run($mageRunCode, $mageRunType);`:

```php
// Insert this code before the last line `Mage::run($mageRunCode, $mageRunType);`
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && $err['type'] != E_WARNING) {
        $err['type'] = $err['type'] . ':' . array_search($err['type'], get_defined_constants(true)['Core']);
        $err['uri'] = $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'];
        [$err['user'], $err['role']] = Mage::helper('shooter')->getSessionUser();
        Mage::getModel('core/flag', ['flag_code' => 'error_get_last'])
            ->loadSelf()
            ->setFlagData($err)
            ->save();
    }
});
```
The error captured is displayed with URI `/shooter/log`.

### Show Information in the Browser

```
{http://your_domain}/shooter/info # Display PHP information
{http://your_domain}/shooter/info/ver # Display OpenMage version
{http://your_domain}/shooter/info/server # Display server information
{http://your_domain}/shooter/info/redis # Display Redis info
```

Refer to [InfoController.php](app/code/community/Kiatng/Shooter/controllers/InfoController.php) for more details.

### REST OAuth 1.0a Tester
The tester allows you to test the OpenMage REST API as a consumer interactively with a browser. Use the following URI to access it:

```
{http://your_domain}/shooter/rest
```

The OAuth credentials are only stored in the session and never saved anywhere else.

Refer to [RestController.php](app/code/community/Kiatng/Shooter/controllers/RestController.php) for more details.

#### Test SSL Connection
For public accessible URLs, tools like [SSL Labs](https://www.ssllabs.com/ssltest/analyze.html) may be preferred. But if the site is private or under development, this tester is useful:

```
{http://your_domain}/shooter/ssl?url={url}
```

Refer to [SslController.php](app/code/community/Kiatng/Shooter/controllers/SslController.php) for more details.

## Configuration

### Customizing Customer IDs for Access

By default, the module allows the first 20 customers (with IDs from 1 to 20) to access the output in the browser. To customize this behavior, you can define the allowed customer IDs in your module `config.xml` configuration file.

1. Open the file `etc/config.xml` in in your module.
2. Add the following configuration under the `<config>` section:

    ```xml
    <config>
        <!-- Add the following nodes -->
        <shooter>
            <access>
                <up_to_ids>5</up_to_ids> <!-- For the first 5 customer IDs  -->
                <other_ids>100,200</other_ids> <!-- Add additional IDs separated by commas  -->
            </access>
        </shooter>
        <!-- end config -->
    </config>
    ```

3. Save the file and clear the `config` cache to apply the changes.
4. Because the access is saved in frontend session, re-login may be required.
4. If your config doesn't work, add dependency in `/app/etc/modules/Your_Module.xml`:

    ```xml
    <config>
        <modules>
            <Your_Module>
                <active>true</active>
                <codePool>local</codePool>
                <depends> <!-- Add dependency here and clear the `config` cache -->
                    <Kiatng_Shooter />
                </depends>
            </Your_Module>
      </modules>
    </config>
    ```

## Installation

### modman
Use [modman](https://github.com/colinmollenhour/modman) to install the module, open a bash terminal and run:

```bash
modman clone https://github.com/kiatng/openmage-shooter
```

### Composer
Open a bash terminal and run:
```bash
composer require kiatng/openmage-shooter
```

### Manual Installation

Download the files in the `app` directory to your OpenMage root directory.

## Contributing

We welcome contributions! Here's how you can help:

1. Fork the repository
2. Create a feature branch
3. Submit a Pull Request with your changes
4. Update documentation as needed

Please follow our coding standards and include appropriate documentation with your contributions.

### Contributors ✨

Thanks goes to these wonderful people ([emoji key](https://allcontributors.org/docs/en/emoji-key)):

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->

<!-- markdownlint-restore -->
<!-- prettier-ignore-end -->
<!-- ALL-CONTRIBUTORS-LIST:END -->

## License

@copyright 2024-2025 Ng Kiat Siong
This module is licensed under the GNU GPL v3.0.
