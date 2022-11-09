<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::with('authors', 'category', 'editorial', 'bookDownload')
            ->orderBy('title', 'asc')
            ->get();
        return $this->getResponse200($books);
    }

    public function show($id)
    {
        try {
            if (Book::where('id', $id)->exists()) {
                $book = Book::with('authors', 'category', 'editorial', 'bookDownload')
                    ->where('id', $id)
                    ->first();
                return $this->getResponse200($book);
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
            $isbn = preg_replace('/\s+/', '', $request->isbn); //Remove blank spaces from ISBN
            $existIsbn = Book::where("isbn", $isbn)->exists(); //Check if a registered book exists (duplicate ISBN)
            if (!$existIsbn) { //ISBN not registered
                $book = new Book();
                $book->isbn = $isbn;
                $book->title = $request->title;
                $book->description = $request->description;
                $book->published_date = date('y-m-d h:i:s'); //Temporarily assign the current date
                $book->category_id = $request->category["id"];
                $book->editorial_id = $request->editorial["id"];
                $book->save();
                foreach ($request->authors as $item) { //Associate authors to book (N:M relationship)
                    $book->authors()->attach($item);
                }
                $book->bookDownload()->create([]);
                return $this->getResponse201('book', 'created', $book);
            } else {
                return $this->getResponse500(['The isbn field must be unique']);
            }
        } catch (Exception $e) {
            return $this->getResponse500([]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (Book::where('id', $id)->exists()) {
                $book = Book::with('authors', 'category', 'editorial', 'bookDownload')
                    ->where('id', $id)
                    ->first();
                if ($request->isbn) {
                    $isbn = preg_replace('/\s+/', '', $request->isbn);
                    if (Book::where("isbn", $isbn)->exists() && $book->id != $isbn)
                        return $this->getResponse500(['The isbn field must be unique']);
                    $book->isbn = $isbn;
                }
                if ($request->title) $book->title = $request->title;
                if ($request->description) $book->description = $request->description;
                if ($request->published_date) $book->published_date = $request->published_date;
                if ($request->category) $book->category_id = $request->editorial['id'];
                if ($request->editorial) $book->editorial_id = $request->editorial['id'];
                $book->save();
                if ($request->authors) $book->authors()->sync(
                    array_map(
                        fn($author) => $author['id'],
                        $request->authors
                    )
                );
                $book->refresh();
                return $this->getResponse201('book', 'updated', $book);
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
            if (Book::where('id', $id)->exists()) {
                $book = Book::with('authors', 'category', 'editorial', 'bookDownload')
                    ->where('id', $id)
                    ->first();
                $book->authors()->detach();
                $book->bookDownload()->delete();
                $book->delete();
                return $this->getResponse201('book', 'deleted', $book);
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            return $this->getResponse500([]);
        }
    }
}
