<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\BookReview;
use App\Models\Book;

class BookReviewController extends Controller
{
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
    
    // Permite al usuario autenticado editar alguna de las reseñas emitidas. 
    
    // Para este punto, toma en cuenta lo siguiente:
    
    // - Se requiere recibir la variable de ruta $id, la cual corresponde al Id del comentario a modificar.
    // - El valor del campo “edited” debe cambiar a true.
    // - Los usuarios solo pueden modificar sus reseñas. En caso contrario se sugiere retornar el siguiente código de estado HTTP.
}
