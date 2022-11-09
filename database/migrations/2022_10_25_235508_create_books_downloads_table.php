<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBooksDownloadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('books_downloads', function (Blueprint $table) {
            $table->id();
            $table->integer('total_downloads')->default(0);
            $table->unsignedBigInteger('book_id');
        });

        Schema::table('books_downloads', function (Blueprint $table) {
            $table->foreign('book_id')->references('id')->on('books');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('books_downloads');
    }
}
