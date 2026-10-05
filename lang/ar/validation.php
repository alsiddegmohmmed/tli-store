<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'حقل :attribute يجب أن يكون نصًا.',
    'email' => 'أدخل بريدًا إلكترونيًا صالحًا.',
    'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
    'decimal' => 'عدد الخانات العشرية في :attribute غير صالح.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'max' => ['string' => 'حقل :attribute يتجاوز الحد المسموح (:max حرفًا).', 'numeric' => 'قيمة :attribute تتجاوز :max.'],
    'min' => ['string' => 'حقل :attribute يحتاج :min حرفًا على الأقل.', 'numeric' => 'قيمة :attribute يجب ألا تقل عن :min.'],
    'between' => ['numeric' => 'قيمة :attribute يجب أن تكون بين :min و:max.'],
    'attributes' => ['email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور'],
];
