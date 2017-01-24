## Jumplinks for ProcessWire

[![GitHub tag](https://img.shields.io/badge/release-1.5.48-blue.svg?style=flat-square)](https://github.com/rockettpw/jumplinks/releases) [![license](https://img.shields.io/github/license/rockettpw/jumplinks.svg?maxAge=2592000&style=flat-square)](https://github.com/rockettpw/jumplinks/blob/master/LICENSE.md) [![v2](https://img.shields.io/badge/v2-pending-lightgray.svg?style=flat-square)](https://github.com/rockettpw/jumplinks/issues/14)

As of version 1.5.0, Jumplinks requires at least ProcessWire 2.6.1 to run. At the time of writing this, version 3 on the development branch is also supported.

**In Development:** Jumplinks 2, a complete rewrite, is in the works. You can track the status of behind-closed-doors development [here](https://github.com/rockettpw/jumplinks/issues/14). Once an alpha-candidate is ready, it will be pushed to its own branch with the details. Documentation will follow shortly thereafter.

---

Jumplinks is an enhanced version of the original [ProcessRedirects](http://modules.processwire.com/modules/process-redirects/) by [Antti Peisa](https://twitter.com/apeisa).

The `Process` module manages your permanent and temporary redirects (we'll call these "jumplinks" from now on, unless in reference to redirects from another module), useful for when you're migrating over to ProcessWire from another system/platform.

Each jumplink supports [wildcards](https://rockett.pw/jumplinks/wildcards), shortening the time needed to create them.

Unlike similar modules for other platforms, wildcards in Jumplinks are much easier to work with, as Regular Expressions are not fully exposed. Instead, parameters wrapped in `{curly braces}` are used - these are described in the documentation.

---

### Quick Installation

1. Under *Modules*, go to the *New* tab.
2. Enter the class name (*ProcessJumplinks*) and click *Download & Install*.

### Manual Installation

1. Download the module from [here](https://github.com/rockettpw/jumplinks/archive/master.zip)
2. Copy the *ProcessJumplinks* folder to `site/modules`
3. Go to *Modules* in your admin panel, and find *ProcessJumplinks*. If it's not listed, you'll need to click *Refresh*.
4. Install the module and configure it, if needed.

---

### Documentation & Support

You can view the documentation **[here](https://rockett.pw/jumplinks)**, and get support **[in the forums](https://processwire.com/talk/topic/8697-jumplinks/)**.

### Contributing & License

If you have any issues to report (such as a bug or oversight), please use the [issue tracker](https://github.com/mikerockett/ProcessJumplinks/issues).

Module is released under the **[MIT License](https://mit-license.org/)**.
