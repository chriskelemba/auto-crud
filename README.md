# Auto Crud – Zero-Code CRUD Generator for Laravel

![Auto CRUD](images/auto-crud.png)

**Auto Crud** is a powerful Laravel package that gives you fully functional RESTful APIs, soft deletes, and file upload/download endpoints — **with just an empty controller**.

No routes file changes.  
No repetitive controller methods.  
No resource classes required (but supported).  
Just extend one class → instant API.

---

## Features

- 100% automatic CRUD (index, show, store, update, destroy)
- Soft delete support (trashed, restore, force-delete)
- Built-in file management (single + multiple upload, download, replace)
- Automatic model & resource detection using naming conventions
- Laravel Resource class integration (if exists)
- Works with standard `app/Http/Controllers` and modular structure (`app/Modules/*`)
- Automatic route registration under `/api` with `api` middleware
- Validation rules, eager loading, ordering — all customizable with protected properties

---

## Installation

```bash
composer require chriskelemba/api-auto-crud
```

---

## Usage

All you require to do is to extend the package's controller file.

```php
<?php

namespace App\Http\Controllers;

use AutoCrud\Http\Controllers\Controller;

class UserController extends Controller
{
    //
}
```

---

## Auto-Generated CRUD Routes
For every controller that extends AutoCrud\Http\Controllers\Controller, the package automatically registers a full CRUD route set under the /api prefix.

Assuming a controller named:

```php
<?php

namespace App\Http\Controllers;

use AutoCrud\Http\Controllers\Controller;

class UserController extends Controller
{
    //
}
```

The base route becomes:

```bash
/api/users
```

Below is the complete routing table generated for every auto-CRUD controller:

```md
## Standard CRUD Routes
| Method | URI                | Action  | Route Name     |
|--------|---------------------|----------|-----------------|
| GET    | /api/users          | index    | users.index     |
| POST   | /api/users          | store    | users.store     |
| GET    | /api/users/{id}     | show     | users.show      |
| PUT    | /api/users/{id}     | update   | users.update    |
| DELETE | /api/users/{id}     | destroy  | users.destroy   |

## Soft Delete Routes
(Only active if the model uses SoftDeletes)
| Method | URI                          | Action       | Route Name        |
|--------|-------------------------------|--------------|--------------------|
| GET    | /api/users/trashed           | trashed      | users.trashed      |
| POST   | /api/users/{id}/restore      | restore      | users.restore      |
| DELETE | /api/users/{id}/force        | forceDelete  | users.forceDelete  |

## File Handling Routes
| Method | URI                               | Action             | Route Name             |
|--------|------------------------------------|---------------------|-------------------------|
| POST   | /api/users/upload                  | uploadFile         | users.upload            |
| POST   | /api/users/{id}/upload             | updateFile         | users.updateFile        |
| POST   | /api/users/uploads/multiple        | uploadMultiple     | users.uploadMultiple    |
| GET    | /api/users/download/{id}           | downloadFile       | users.download          |
| DELETE | /api/users/delete-file/{id}        | users.deleteFile   | deleteFile              |
```

>⚠️ **Model Requirement**

>This package requires every controller to be provided with a model.
>If the model is not defined, the controller cannot perform CRUD, soft delete, or file handling operations.
>Make sure your configuration specifies the model used by each controller.

---

## Requirements

- Laravel 12
- PHP 8.2+

## Contributing

Contributions are open to everyone.  
Feel free to submit issues, feature requests, or pull requests.

## License

This package is open-source software licensed under the MIT License.