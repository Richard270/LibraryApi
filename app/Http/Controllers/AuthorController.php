<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Author;

class AuthorController extends Controller
{
    public function index()
    {
        $authors = Author::with('books')
            ->orderBy('name', 'asc')
            ->get();
        return $this->getResponse200($authors);
    }

    public function show($id)
    {
        try {
            if (Author::where('id', $id)->exists()) {
                $author = Author::with('books')
                    ->where('id', $id)
                    ->first();
                return $this->getResponse200($author);
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            return $this->getResponse500([]);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $author = new Author();
            $author->name = $request->name;
            $author->first_surname = $request->first_surname;
            if ($request->second_surname) $author->second_surname = $request->second_surname;
            $author->save();
            if ($request->books) {
                foreach ($request->books as $book) {
                    $book->books()->attach($book);
                }
            }
            DB::commit();
            return $this->getResponse201('author', 'created', $author);    
        } catch (Exception $e) {
            DB::rollbackTransaction();
            return $this->getResponse500([]);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            if (Author::where('id', $id)->exists()) {
                $author = Author::with('books')
                    ->where('id', $id)
                    ->first();
                if ($request->name) $author->name = $request->name;
                if ($request->first_surname) $author->first_surname = $request->first_surname;
                if ($request->second_surname) $author->second_surname = $request->second_surname;
                $author->save();
                if ($request->books) $author->books()->sync(
                    array_map(
                        fn($book) => $book['id'],
                        $request->books
                    )
                );
                $author->refresh();
                DB::commit();
                return $this->getResponse201('author', 'updated', $author);
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            DB::rollbackTransaction();
            return $this->getResponse500([]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            if (Author::where('id', $id)->exists()) {
                $author = Author::with('books')
                    ->where('id', $id)
                    ->first();
                $author->books()->detach();
                $author->delete();
                DB::commit();
                return $this->getResponseDelete200('author');
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            DB::rollbackTransaction();
            return $this->getResponse500([]);
        }
    }
}
