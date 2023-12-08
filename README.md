# Instagram

A Drupal module for importing Instagram posts to entities in Drupal.

## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires the library [Instagram user feed PHP](https://github.com/pgrimaud/instagram-user-feed)
which is installed automatically if you install the module using composer.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

Configuration is only possible via the modules settings file
`instag.settings.yml`. Edit the file in you config/sync directory and add
your Instagram username and password.

```yaml
username: instag
password: instag
```

## Maintainers

- Peter Törnstrand - [Peter Törnstrand](https://www.drupal.org/u/peter-t%C3%B6rnstrand)

