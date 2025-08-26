<?php

namespace App\Http\Controllers\GraphSchema;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EdgePropertyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:graph-schema.manage');
    }
}
