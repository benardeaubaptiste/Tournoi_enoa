<?php

namespace tournoi_enoa\Entities;

class Admin
{
    public $id;
    public $username;
    public $password_hash;
    public $display_name;
    public $role;
    public $is_active;
    public $last_login_at;
    public $created_at;
    public $updated_at;
}
