<?php

namespace App\Jobs;

use App\Services\ZohoWorkdriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class ProcessBigcommerceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file_name;
    protected $file_path_to_save;
    protected $outputFileName;
    protected $outputFilePath;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($file_name, $file_path_to_save, $outputFileName, $outputFilePath)
    {
        $this->file_name = $file_name;
        $this->file_path_to_save = $file_path_to_save;
        $this->outputFileName = $outputFileName;
        $this->outputFilePath = $outputFilePath;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Paso 1: Descargar el archivo Excel desde Zoho WorkDrive
            $serviceWorkdrive = new ZohoWorkdriveService();
            $download_response = $serviceWorkdrive->downloadFilev2($this->file_name, env('ZOHO_WORKDRIVE_FOLDER_INPUT_XLS'), $this->file_path_to_save);
            
            // Validación de descarga exitosa
            if (!$download_response['status']) {
                Log::error('Error al descargar el archivo desde Zoho WorkDrive: ' . $download_response['message']);
                return;
            }

            // Paso 2: Procesar el archivo Excel a CSV
            $inputFilePath = $this->file_path_to_save . $this->file_name;
            $startTime = now();
            $this->processExcelFile($inputFilePath, $this->outputFilePath, $startTime);

            $endTime = now();
            $duration = $startTime->diffInMinutes($endTime);

            // Validación de procesamiento exitoso
            if (!file_exists($this->outputFilePath)) {
                Log::error('Error al procesar el archivo Excel a CSV.');
                return;
            }

            // Paso 3: Subir el archivo CSV a Zoho WorkDrive
            $upload_file = $serviceWorkdrive->uploadFile($this->outputFileName, env('ZOHO_WORKDRIVE_FOLDER_OUTPUT_CSV'), $this->outputFilePath);

            // Validación de subida exitosa
            if ($upload_file['status'] !== 'SUCCESS') {
                Log::error('Error al subir el archivo CSV a Zoho WorkDrive.');
                return;
            }

            // Generar link compartido para el archivo subido
            $resource_id = $upload_file['data'][0]['attributes']['resource_id'];
            $share_file = $serviceWorkdrive->createExternaLinks($resource_id, $this->outputFileName);

            $link = null;
            if (!empty($share_file->data)) {
                $attributes = $share_file->data->attributes ?? null;
                if (!empty($attributes->link)) {
                    $link = $attributes->link;
                }
            }

            // Log success message with link
            Log::info('Archivo procesado y subido correctamente. Enlace compartido: ' . ($link ?? 'No se pudo generar el enlace compartido.'));

        } catch (\Exception $e) {
            Log::error('Ocurrió un error durante el proceso: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
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
}