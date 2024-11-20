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
- OAuth 1.0a Tester

**CAUTION**: This module is designed for development environments. It may not be suitable for production environments due to the potential for exposing sensitive information.

## Table of Contents

1. [Quick Start](#quick-start)
    - [Debug Helper](#debug-helper)
    - [Output the Log Files in the Browser](#output-the-log-files-in-the-browser)
    - [Capture the Last Error](#capture-the-last-error)
    - [Show Information in the Browser](#show-information-in-the-browser)
    - [REST OAuth 1.0a Tester](#oauth-10a-tester)
2. [Installation](#installation)
    - [Composer](#composer)
    - [Manual Installation](#manual-installation)
3. [Contributing](#contributing)
4. [License](#license)

## Quick Start

You need to be able to access the customers in the database. The first 20 customers with ID from 1 to 20 are allowed to view the output in the browser. You can edit the `entity_id` in the table `customer_entity` for these users.

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
```

Refer to [InfoController.php](app/code/community/Kiatng/Shooter/controllers/InfoController.php) for more details.

### REST OAuth 1.0a Tester

```
{http://your_domain}/shooter/rest
```

The OAuth credentials are only stored in the session and never saved anywhere else.

Refer to [RestController.php](app/code/community/Kiatng/Shooter/controllers/RestController.php) for more details.

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

We welcome contributions:

1. Fork the repository
2. Create a feature branch
3. Submit a Pull Request with your changes
4. Update documentation as needed

Please follow our coding standards and include appropriate documentation with your contributions.

## License

@copyright 2024 Ng Kiat Siong
This module is licensed under the GNU GPL v3.0.
