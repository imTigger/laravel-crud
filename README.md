# Laravel CRUD
Laravel CRUD Controller &amp; Generator

- WIP, Current for internal testing

# Dependencies

This package depend on the following packages to generate easy-to-use yet customizable controller for basic CRUD operations

- Laravel DataTables - https://github.com/yajra/laravel-datatables
- Laravel Form Builder - https://github.com/kristijanhusak/laravel-form-builder

Optional Features:

 - Laratrust ACL - https://github.com/santigarcor/laratrust
 - Translatable - https://github.com/dimsav/laravel-translatable
 - Sortable - https://github.com/boxfrommars/rutorika-sortable


# Installation

`composer require imtigger/laravel-crud`

# Usage

CRUD generator

```
php artisan make:crud --help
Usage:
  make:crud [options] [--] <name>

Arguments:
  name

Options:
      --no-model        Generates no model
      --no-view         Generates no view
      --no-controller   Generates no controller
      --no-form         Generates no form
      --no-migration    Generates no migration
      --no-soft-delete  No soft delete
      --no-ui           Shortcut for --no-view, --no-controller and --no-form
```

CRUD index view Datatable header generator

```
php artisan make:crud:header --help
Usage:
  make:crud:header <table>

Arguments:
  table
```

Translation string generator

```
php artisan make:crud:trans --help
Usage:
  make:crud:trans
```

# CRUDController 

To be continued.
