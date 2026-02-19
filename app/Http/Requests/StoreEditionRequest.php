<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreEditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:51200', // 50MB in kilobytes
            ],
            'publication_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Debe seleccionar un archivo PDF.',
            'file.mimes' => 'El archivo debe ser un PDF válido.',
            'file.max' => 'El archivo no puede superar los 50 MB.',
            'publication_date.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'publication_date.before_or_equal' => 'La fecha de publicación no puede ser futura.',
        ];
    }
}
