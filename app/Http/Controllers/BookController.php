<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Book;
use App\Models\BookReview;

// NOTA SOBRE VALIDATOR
// El método make se encarga de definir las restricciones que estamos validando
// pero no devuelve el valor booleano que indica si no una instancia de validador
// Es posible validar de forma directa usando el método validate() de la clase Request
// pero Garduño nos enseñó a usarlo mediane la instancia de la clase Validator así que pues eso
// el método fails() devuelve un valor booleano indicando si ocurrió un fallo
// en caso de haberlos, los errores se pueden obtener en forma de arreglo mediante el método errors()

class BookController extends Controller
{
    public function index()
    {
        // Consulta de libros
        // Primero estoy mandando a llamar todas las relaciones con las que cuenta mi modelo
        // para que en la consulta muestre los objetos correspondientes
        $books = Book::with('authors', 'category', 'editorial', 'bookDownload', 'bookReviews')
            // Posteriormente aplico un ordenamiento por título de forma ascendente
            ->orderBy('title', 'asc')
            // Y finalmente ejecuto la consulta, en este caso, con un get
            // que me devuelve un arreglo de objetos
            ->get();
        // Devuelvo la lista obtenida en una respuesta 200
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
    
    // Actualizar review
    // 
    public function updateBookReview(Request $request, $id)
    {
        // Validación de información proveniente del front end
        $validator = Validator::make($request->all(), [
            // Para actualizar una review solo se requiere el comentario
            // y este debe estar presente obligatoriamente
            'comment' => 'required',
        ]);
        // Antes que nada reviso si la validación salió bien
        // Si no, ni siquiera tiene caso seguir con lo demás
        // En caso de que falle devuelve una respuesta con código 500 que contiene el arreglo
        // de errores que devuelve validator
        if ($validator->fails()) return $this->getResponse500([$validator->errors()]);
        // Verifico si el registro que estoy intentando modificar existe o no
        // En caso de no existir, devuelvo un error 404 not found
        if (!BookReview::where('id', $id)->exists()) return $this->getResponse404();
        // Habiendo comprobado que mi review existe, hago la consulta para traerla
        // Estoy llamando el método with aquí porque al finalizar el update me devuelve el objeto
        // y quiero que cuando me llegue eso al postman se vean los objetos de las relaciones
        // pero puede que no desees hacer eso tú, es cuestión de gustos, pero no parte del proceso de actualizar
        $bookReview = BookReview::with('user', 'book')
            // Filtro por id, ya que solo quiero el registro que mi usuario indicó en la ruta
            ->where('id', $id)
            // Ejecuto la consulta con first(), ya que este devuelve un objeto, a diferencia de get()
            // que devuelve un arreglo, si usara get tendría que agregar también [0] para obtener mi objeto
            ->first();
        // Valido que la review pertenezca al usuario que está intentando modificarla
        // en caso de no hacerlo, devuelvo una respuesta 403 que indica que el usuario no está autorizado
        // para realizar esa acción
        if ($bookReview->user->id != $request->user()->id ) return $this->getResponse403();
        // Ya que finalicé con las validaciones inicio el bloque try y la transacción
        // Es importante mencionar que a veces las validaciones también pueden tronar
        // así que hay que analizar el código para ver lo que debe estar dentro o fuera del try catch
        // En caso de que te sea complicado, la solución más segura es que todo esté dentro del try/transacción
        // no sería lo mejor, pero es inofensivo y más deseable que dividirlo mal
        // También es importante mencionar que la transacción debe iniciar antes del try o en su defecto en
        // la primera instrucción de este, no importa que lo primero que hagas dentro del try no
        // tenga que ver con la base de datos
        // Esto es porque en el catch siempre se ejecuta el rollback, entonces si llegaras a poner
        // el inicio de la transacción muy abajo, puede que algo tronara antes de iniciarla, y en el
        // catch estarías ejecutando la instrucción rollback sin tener ninguna transacción, lo que podría
        // lanzar otro error, que además ya no estaría dentro del try y por lo tanto llegaría hasta el usuario
        DB::beginTransaction();
        try {
            // Empiezo a llenar mis atributos, es remarcable recordar la forma en que hacía esta acción
            // en mis prácticas pasadas. Si recuerdas bien, siempre metía todo en un if, por ejemplo
            // if ($request->comment) $bookReview->comment = $request->comment;
            // Esto es para que valide que si venga algo en el request. En este caso específico
            // no hace falta porque en las validaciones le pusimos required al comment, que es lo único
            // que estamos recibiendo del cuerpo de la petición. No obstante, en un update como el de libro
            // seguiríamos necesitando validar, porque simplemente no puedes obligar al usuario a que cambie
            // todos los atributos en el update, y volver a mandar todo el objeto sería inncecesario y
            // afectaría al rendimiento del sistema
            $bookReview->comment = $request->comment;
            // Establezco el atributo edited en true, ya que acaba de ser editado
            $bookReview->edited = true;
            // Guardo los cambios, save() registra una entidad cuando lo usamos con una instancia nueva
            // y actualiza cuando lo usamos con una instancia que ya existe en la base
            $bookReview->save();
            // Nada puede tronar en este punto, por lo que se cierra la transacción
            DB::commit();
            return $this->getResponse201('book review', 'updated', $bookReview);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }  
    }
}
