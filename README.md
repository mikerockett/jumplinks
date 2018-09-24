## Jumplinks v1 for ProcessWire

> Jumplinks 2, a complete rewrite, is in the works. Once an alpha-candidate is ready, the repo will be opened up for testing.

Jumplinks is an enhanced version of the original [ProcessRedirects](http://modules.processwire.com/modules/process-redirects/) by [Antti Peisa](https://twitter.com/apeisa).

The `Process` module manages your permanent and temporary redirects (we'll call these "jumplinks" from now on, unless in reference to redirects from another module), useful for when you're migrating over to ProcessWire from another system/platform.

Each jumplink supports [wildcards](https://jumplinks.rockett.pw/wildcards), shortening the time needed to create them.

Unlike similar modules for other platforms, wildcards in Jumplinks are much easier to work with, as Regular Expressions are not fully exposed. Instead, parameters wrapped in `{curly braces}` are used - these are described in the documentation.

As of version 1.5.0, Jumplinks requires at least ProcessWire 2.6.1 to run. At the time of writing this, version 3 on the development branch is also supported.

---

### Quick Installation

1. Under *Modules*, go to the *New* tab.
2. Enter the class name (*ProcessJumplinks*) and click *Download & Install*.

### Manual Installation

1. Download the module from [here](https://gitlab.com/rockettpw/seo/jumplinks-one/-/archive/master/jumplinks-one-master.zip)
2. Copy the *ProcessJumplinks* folder to `site/modules`
3. Go to *Modules* in your admin panel, and find *ProcessJumplinks*. If it's not listed, you'll need to click *Refresh*.
4. Install the module and configure it, if needed.

---

### Documentation & Support

You can view the documentation **[here](https://jumplinks.rockett.pw)**, and get support **[in the forums](https://processwire.com/talk/topic/8697-jumplinks/)**.

### Contributing & License

If you have any issues to report (such as a bug or oversight), please use the [issue tracker](https://gitlab.com/rockettpw/seo/jumplinks-one/issues).

Module is released under the **[ISC License](LICENSE.md)**. The CSV package by The League of Extraordinary Packages is licensed under the [MIT License](https://github.com/thephpleague/csv/blob/master/LICENSE).
