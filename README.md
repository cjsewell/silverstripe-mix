# Laravel Mix support for SilverStripe

Adds basic Laravel Mix support to SilverStripe CMS.
Supports hot module reloading

See [ssmix/bootstrap-theme](https://github.com/cjsewell/silverstripe-mix-bootstrap-theme/) for an example theme using ssmix

## Requirements

* SilverStripe ^4.0.

## License
See [License](license.md)

## Documentation
### Template Requirements API

**/themes/&lt;my-theme-dir&gt;/templates/SomeTemplate.ss**

```ss
$mix("/js/my-js-file.js")
$mix("/css/my-css-file.css")
```

### PHP Requirements API
```php
use function SSMix\mix;

class MyCustomController extends Controller
{
    protected function init()
    {
        parent::init();

        mix("/js/my-js-file.js");
        mix("/css/my-css-file.css");
    }
}

```
...to do


## Example configuration (optional)
...to do

## Maintainers
 * Corey Sewell <corey@sewell.net.nz>

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
