<?php

namespace PHPSTORM_META {

    override(\Illuminate\Database\Eloquent\Model::mixin(0), type(0));
    override(\Illuminate\Support\Facades\Auth::user(0), type(0));
    
    // Laravel Test Helpers
    override(\Illuminate\Foundation\Testing\TestCase::postJson(0), map([
        '' => '@\Illuminate\Testing\TestResponse',
    ]));
    
    override(\Illuminate\Foundation\Testing\TestCase::getJson(0), map([
        '' => '@\Illuminate\Testing\TestResponse',
    ]));
    
    override(\Illuminate\Foundation\Testing\TestCase::withToken(0), map([
        '' => '@\Illuminate\Foundation\Testing\TestCase',
    ]));
    
    // Match Model Alias
    override(\App\Models\Match::where(0), map([
        '' => '@\Illuminate\Database\Eloquent\Builder|\App\Models\Match',
    ]));
    
    override(\App\Models\Match::find(0), map([
        '' => '\App\Models\Match|null',
    ]));
    
    override(\App\Models\Match::create(0), map([
        '' => '\App\Models\Match',
    ]));
}
