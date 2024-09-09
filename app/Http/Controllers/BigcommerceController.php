<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ZohoOAuthService;
use App\Services\ZohoCRMService;
use App\Services\ZohoWorkdriveService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Jobs\ProcessBigcommerceJob;


use Maatwebsite\Excel\Facades\Excel;

class BigcommerceController extends Controller
{

/**
     * @OA\Get(
     *     path="/api/createProducts",
     *     summary="createProducts",
     *     description="create Product In Crm of Workdrive",
     *     tags={"Bigcommerce"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="API test route works!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */

    public function createProductInCrm(Request $request)
    {
        //$serviceCRM = new ZohoCRMService();
        //$record = $serviceCRM->uploadFileCrm();
        try {
            $serviceCRM = new ZohoCRMService();
            $record = $serviceCRM->uploadFileCrm();

            // Si se llega hasta aquí sin errores, devuelve un éxito
            return response()->json([
                'status' => 'success',
                'message' => 'El archivo se subió y el trabajo de escritura de productos se inició correctamente.',
                'data' => $record // Puedes devolver los datos relevantes aquí si es necesario
            ], 200);

        } catch (\Exception $e) {
            // Captura cualquier excepción y devuelve el error con detalles
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString() // Esto es opcional, puedes eliminarlo si no quieres mostrar el trace
            ], 500);
        }
    }

/**
     * @OA\Get(
     *     path="/api/uploadFileCsv",
     *     summary="Test API",
     *     description="Upload CSV File to Workdrive",
     *     tags={"Bigcommerce"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="API test route works!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */

     public function uploadFileCsv()
     {

        $file_name = 'testsubidacsv.csv';   /* nombre archivo csv**/
        $file_path = storage_path('app/public/output_file/' . $file_name);
        $serviceWorkdrive = new ZohoWorkdriveService();
        $upload_file = $serviceWorkdrive->uploadFile($file_name, env('ZOHO_WORKDRIVE_FOLDER_OUTPUT_CSV'), $file_path);
        if ($upload_file['status'] == 'SUCCESS') {
                        $resource_id = $upload_file['data'][0]['attributes']['resource_id'];
                        $share_file = $serviceWorkdrive->createExternaLinks($resource_id, $file_name);

                        if (!empty($share_file->data)) {
                            if (!empty($share_file->data->attributes)) {
                                $attributes = $share_file->data->attributes;
                                $link = $attributes->link;


                            }
                        }
                   //return ResponseHelper::error($th->getMessage(), 400);

        }
     }


 /**
     * @OA\Get(
     *     path="/api/downloadFileExcel",
     *     summary="Download Excel File from Workdrive",
     *     description="Download Excel File from Workdrive",
    *     tags={"Bigcommerce"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="archivo descargado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function downloadFileExcel()
    {
        $file_name = env('FILE_NAME_XLSX'); //archivo a descargar
        $file_path_to_save = storage_path(env('INPUT_FILE_PATH'));
        $serviceWorkdrive = new ZohoWorkdriveService();
        $respuesta = $serviceWorkdrive->downloadFile($file_name, env('ZOHO_WORKDRIVE_FOLDER_INPUT_XLS'), $file_path_to_save);

    }

     /**
     * @OA\Get(
     *     path="/api/downloadFileCsv",
     *     summary="Download Csv File from Workdrive",
     *     description="Download Csv File from Workdrive",
    *     tags={"Bigcommerce"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="archivo descargado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function downloadFileCsv()
    {
        try {
            // Descargar el archivo CSV
            $fileName = 'tiny.csv';
            $fileSavePath = storage_path('app/public/input_file/');
            $serviceWorkdrive = new ZohoWorkdriveService();
            $response = $serviceWorkdrive->downloadFile($fileName, env('ZOHO_WORKDRIVE_FOLDER_OUTPUT_CSV'), $fileSavePath);

            // Validar si la descarga fue exitosa
            if ($response['status']) {
                // Leer y procesar el archivo CSV si la descarga fue exitosa
                $csvFilePath = $response['file_path'];
                $data = $this->readCsv($fileName);

                // Insertar los datos en Zoho CRM
                $serviceWorkdrive->insertDataIntoZohoCRM($data);

                return response()->json(['message' => 'Archivo procesado e insertado en Zoho CRM exitosamente']);
            } else {
                // Manejar el caso en que la descarga falla
                return response()->json(['error' => 'Error al descargar el archivo: ' . $response['message']], 500);
            }
        } catch (\Exception $e) {
            // Manejar cualquier otra excepción
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function readCsv($fileName)
    {
        $filePath = storage_path('app/public/input_file/' . $fileName);

        $data = Excel::toArray([], $filePath);
        // La data estará en la primera hoja (índice 0) como un array de arrays.
        return $data[0];
    }


    /**
     * @OA\Get(
     *     path="/api/process-excel",
     *     summary="Process Excel file and convert to CSV format",
     *     description="Process Excel file and convert to CSV format",
     *     tags={"Bigcommerce"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="API test route works!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */

     public function processExcelCsv()
     {
        try {
            // Rutas y nombres de archivos
            $inputFilePath = storage_path(env('INPUT_FILE_PATH').env('FILE_NAME_XLSX'));
            $outputFilePath = storage_path(env('OUTPUT_FILE_PATH') . env('OUTPUT_CSV_NAME'));

            // Capturar tiempo de inicio
            $startTime = now();

            // Procesar el archivo Excel antes de subirlo
            $this->processExcelFile($inputFilePath, $outputFilePath, $startTime);

            // Capturar tiempo de finalización
            $endTime = now();
            $duration = $startTime->diffInMinutes($endTime);

            // Retornar respuesta JSON con estado 200 y detalles del procesamiento
            return response()->json([
                'status' => 'success',
                'message' => 'Archivo procesado correctamente.',
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'duration_minutes' => $duration
            ], 200);
        } catch (\Exception $e) {
            // Capturar tiempo de finalización en caso de error
            $endTime = now();
            $duration = $startTime->diffInMinutes($endTime);

            // Retornar respuesta JSON con estado 500 y detalles del error
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $endTime->toDateTimeString(),
                'duration_minutes' => $duration
            ], 500);
        }
     }

     private function processExcelFile($inputPath, $outputPath, $startTime)
     {
        // Crear un buffer para capturar la salida del comando
        $buffer = new BufferedOutput();

        // Ejecutar el comando Artisan para procesar el archivo Excel
        $exitCode = Artisan::call('excel:processspout:spout', [
            'path' => $inputPath,
            'outputCsv' => $outputPath,
        ], $buffer);

        if ($exitCode !== 0) {
            throw new \Exception('Hubo un problema al procesar el archivo Excel.');
        }

        // Mostrar la salida del comando en los logs (opcional)
        \Log::info($buffer->fetch());
     }

    /**
     * @OA\Get(
     *     path="/api/processbigcommerce",
     *     summary="Process Bigcommerce",
     *     description="Inicia el proceso de Bigcommerce y retorna un mensaje de éxito.",
     *     operationId="processBigcommerce",
     *     tags={"Bigcommerce"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Operación exitosa",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="El proceso ha sido iniciado y se está ejecutando en segundo plano."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error en el servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="error"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ocurrió un error al intentar iniciar el proceso."
     *             )
     *         )
     *     )
     * )
     */


    public function processBigcommerce()
    {
        $file_name = env('FILE_NAME_XLSX');
        $file_path_to_save = storage_path(env('INPUT_FILE_PATH'));
        $outputFileName = env('OUTPUT_CSV_NAME');
        $outputFilePath = storage_path(env('OUTPUT_FILE_PATH') . $outputFileName);

        try {
            ProcessBigcommerceJob::dispatch($file_name, $file_path_to_save, $outputFileName, $outputFilePath);

            return response()->json([
                'status' => 'success',
                'message' => 'El proceso ha sido iniciado y se está ejecutando en segundo plano.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al intentar iniciar el proceso: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

}
