## Jumplinks for ProcessWire

**Current Release:** 1.5.41

As of version 1.5.0, Jumplinks requires at least ProcessWire 2.6.1 to run. At the time of writing this, version 3 on the development branch is also supported.

**In Development:** Jumplinks 2, a complete rewrite, is in the works. You can track the status of behind-closed-doors development [here](https://github.com/rockettpw/jumplinks/issues/14). Once an alpha-candidate is ready, it will be pushed to its own branch with the details. Documentation will follow shortly thereafter.

---

Jumplinks is an enhanced version of the original [ProcessRedirects](http://modules.processwire.com/modules/process-redirects/) by [Antti Peisa](https://twitter.com/apeisa).

The `Process` module manages your permanent and temporary redirects (we'll call these "jumplinks" from now on, unless in reference to redirects from another module), useful for when you're migrating over to ProcessWire from another system/platform.

Each jumplink supports [wildcards](http://rockett.pw/jumplinks/wildcards), shortening the time needed to create them.

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

You can view the documentation **[here](http://rockett.pw/jumplinks)**, and get support **[in the forums](https://processwire.com/talk/topic/8697-jumplinks/)**.

### Contributing & License

If you have any issues to report (such as a bug or oversight), please use the [issue tracker](https://github.com/mikerockett/ProcessJumplinks/issues).

Module is released under the **[MIT License](http://mit-license.org/)**

```
Copyright (c) 2015, Mike Rockett. All Rights Reserved.

The MIT License (MIT)

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the “Software”),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS
OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
IN THE SOFTWARE.
```
