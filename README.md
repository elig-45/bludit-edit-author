# Editable Author Select (Bludit Plugin)

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![Bludit](https://img.shields.io/badge/Bludit-3.x-0d6efd)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3)
[![License](https://img.shields.io/badge/License-GPLv3-brightgreen)](https://www.gnu.org/licenses/gpl-3.0)
![Release](https://img.shields.io/github/v/release/elig-45/bludit-edit-author)

Make the author field editable in the Bludit admin and replace it with a select2 dropdown of existing users (first/last name + nickname). The plugin safely updates the `username` field for pages when an admin changes the author.

## Features

- Make the author field editable in the Bludit admin.
- Dropdown/search on existing users (first name + last name + nickname). UI text is English by default, with French translation when the site language is French.
- Safely updates the page author (`username` field) after create/modify.
- Admin-only author changes.

## Requirements

- Bludit 3.x (tested on 3.16.2)
- PHP 7.4+

## Installation

- Download or clone the repository.
- Rename the folder to `editable-author-select` if necessary.
- Upload it into the `bl-plugins/` directory of your Bludit installation.
- Activate the plugin from the Bludit admin panel (Plugins section).

## Usage

- Open any page (new or existing) in the Bludit admin.
- The "Author" field is replaced by a select2 dropdown of existing users.
- Start typing to search; only existing users can be selected (no custom values).
- Select an author and save the page.
- The page author (`username`) will be updated accordingly.

## Limitations / Notes

- Only admin users are allowed to change the author.
- Selection is restricted to existing users; custom author names are not accepted.
- The plugin does not modify core files, it only uses hooks.

## Development

- Author: elig-45
- License: MIT

## Contributing

Pull requests and suggestions are welcome. Please open an issue to discuss any major changes first.

## Issues

Found a bug or have a question? Open an issue on the repository and include steps to reproduce when applicable.
