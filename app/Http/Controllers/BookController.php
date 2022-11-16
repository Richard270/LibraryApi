<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Book;
use App\Models\BookReview;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::with('authors', 'category', 'editorial', 'bookDownload', 'bookReviews')
            ->orderBy('title', 'asc')
            ->get();
        return $this->getResponse200($books);
    }

    public function show($id)
    {
        try {
            if (Book::where('id', $id)->exists()) {
                $book = Book::with('authors', 'category', 'editorial', 'bookDownload', 'bookReviews')
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
        DB::beginTransaction();
        try {
            $isbn = preg_replace('/\s+/', '', $request->isbn); //Remove blank spaces from ISBN
            $existIsbn = Book::where("isbn", $isbn)->exists(); //Check if a registered book exists (duplicate ISBN)
            if (!$existIsbn) { //ISBN not registered
                $book = new Book();
                $book->isbn = $isbn;
                $book->title = $request->title;
                $book->description = $request->description;
                if ($request->published_date) $book->published_date = $request->published_date;
                else $book->published_date = date('y-m-d h:i:s'); //Temporarily assign the current date
                $book->category_id = $request->category["id"];
                $book->editorial_id = $request->editorial["id"];
                $book->save();
                foreach ($request->authors as $item) { //Associate authors to book (N:M relationship)
                    $book->authors()->attach($item);
                }
                $book->bookDownload()->create([]);
                DB::commit();
                return $this->getResponse201('book', 'created', $book);
            } else {
                return $this->getResponse500(['The isbn field must be unique']);
            }
        } catch (Exception $e) {
            DB::rollbackTransaction();
            return $this->getResponse500([]);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            if (Book::where('id', $id)->exists()) {
                $book = Book::with('authors', 'category', 'editorial', 'bookDownload', 'bookReviews')
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
                DB::commit();
                return $this->getResponse201('book', 'updated', $book);
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
            if (Book::where('id', $id)->exists()) {
                $book = Book::with('authors', 'category', 'editorial', 'bookDownload', 'bookReviews')
                    ->where('id', $id)
                    ->first();
                $book->authors()->detach();
                $book->bookDownload()->delete();
                $book->bookReviews()->delete();
                $book->delete();
                DB::commit();
                return $this->getResponseDelete200('book');
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            DB::rollbackTransaction();
            return $this->getResponse500([]);
        }
    }

    public function addBookReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required',
            'book' => 'required'
        ]);
        if ($validator->fails()) return $this->getResponse500([$validator->errors()]);
        DB::beginTransaction();
        try {
            if (
                BookReview::where('book_id', $request->book['id'])
                    ->where('user_id', $request->user()->id)
                    ->exists()
            )
                return $this->getResponse500(['You have already written a review for this book']);
            if (!Book::where('id', $request->book['id'])->exists())
                return $this->getResponse500(['The entered book does not exists']);
                // return $this->getResponse404();
            $bookReview = new BookReview();
            $bookReview->comment = $request->comment;
            $bookReview->book_id = $request->book['id'];
            $bookReview->user_id = $request->user()->id;
            $bookReview->save();
            DB::commit();
            return $this->getResponse201('book review', 'created', $bookReview);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }  
    }
    
    public function updateBookReview(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required',
        ]);
        if ($validator->fails()) return $this->getResponse500([$validator->errors()]);
        if (!BookReview::where('id', $id)->exists()) return $this->getResponse404();
        $bookReview = BookReview::with('user', 'book')
            ->where('id', $id)
            ->first();
        if ($bookReview->user->id != $request->user()->id ) return $this->getResponse403();
        DB::beginTransaction();
        try {
            $bookReview->comment = $request->comment;
            $bookReview->edited = true;
            $bookReview->save();
            DB::commit();
            return $this->getResponse201('book review', 'updated', $bookReview);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }  
    }
}
