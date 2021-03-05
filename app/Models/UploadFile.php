<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadFile extends Model
{
    protected $table = 'upload_files';

    protected $fillable = [
        'file_type', 'file_name', 'file_size', 'from_lang', 'to_lang', 'job_id', 'target_files', 'status'
    ];
}
