<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
        try {
            $author = new Author();
            $author->name = $request->name;
            $author->first_surname = $request->first_surname;
            $author->second_surname = $request->second_surname;
            $author->save();
            foreach ($request->books as $book) {
                $book->books()->attach($book);
            }
            return $this->getResponse201('author', 'created', $author);    
        } catch (Exception $e) {
            return $this->getResponse500([]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (Author::where('id', $id)->exists()) {
                $author = Author::with('books')
                    ->where('id', $id)
                    ->first();
                if ($request->name) $book->name = $request->name;
                if ($request->first_surname) $book->first_surname = $request->first_surname;
                if ($request->second_surname) $book->second_surname = $request->second_surname;
                $author->save();
                if ($request->books) $author->books()->sync(
                    array_map(
                        fn($book) => $book['id'],
                        $request->books
                    )
                );
                $author->refresh();
                return $this->getResponse201('author', 'updated', $author);
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            return $this->getResponse500([]);
        }
    }

    public function destroy($id)
    {
        try {
            if (Author::where('id', $id)->exists()) {
                $author = Author::with('books')
                    ->where('id', $id)
                    ->first();
                $author->books()->detach();
                $author->delete();
                return $this->getResponse201('author', 'deleted', $author);
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            return $this->getResponse500([]);
        }
    }
}
