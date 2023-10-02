<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Classes extends Migration{
    public function up(){
        Schema::create('classes', function (Blueprint $table){
            $table->id();
            
            $table->string('muzid')->nullable()->unique();
            $table->string('corpusesid')->nullable();
            $table->string('name')->nullable();
            $table->integer('price')->nullable();
            $table->text('img')->default('data:image/png;base64,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');
            $table->string('orders')->nullable();
            $table->timestamp('deleted_at')->nullable();
            
            $table->timestamps();
        });
    }
    public function down(){Schema::dropIfExists('classes');}
}
