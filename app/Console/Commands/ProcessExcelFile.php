<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ProcessExcelFile extends Command
{
    protected $signature = 'excel:process {path} {outputCsv}';
    protected $description = 'Process an Excel file and export the specified columns to a CSV file';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        ini_set('memory_limit', '2G'); // Aumenta el límite de memoria

        try {
            $startTime = now();
            $this->info("Proceso iniciado a las: " . $startTime);

            $path = $this->argument('path');
            $outputCsv = $this->argument('outputCsv');

            // Verificar si el archivo existe
            if (!file_exists($path)) {
                $this->error("El archivo de entrada no existe en la ruta proporcionada.");
                return;
            }

            // Abrir archivo CSV para escritura
            $csvFile = fopen($outputCsv, 'w');

            // Procesar el archivo Excel en bloques
            Excel::import(new class($csvFile) implements ToModel, WithHeadingRow, WithChunkReading {
                protected $csvFile;

                public function __construct($csvFile)
                {
                    $this->csvFile = $csvFile;
                }

                public function model(array $row)
                {
                    $block1 = array_slice($row, 0, 18);
                    $block2Array = array_slice($row, 19, 518);
                    $block2 = [];

                    foreach ($block2Array as $key => $value) {
                        if (!empty($value)) {
                            $block2[$key] = $value;
                        }
                    }
                    $block2Json = json_encode($block2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $block3 = array_slice($row, 519,540);

                    $combinedRow = array_merge($block1, $block3, [$block2Json]);
                    fputcsv($this->csvFile, $combinedRow);
                }

                public function chunkSize(): int
                {
                    return 1000; // Procesar 1000 filas a la vez
                }
            }, $path);

            fclose($csvFile);

            $endTime = now();
            $this->info('El archivo Excel ha sido procesado y el archivo CSV se ha creado correctamente.');
            $this->info("Proceso finalizado a las: " . $endTime);

            $duration = $endTime->diffInSeconds($startTime);
            $this->info("Tiempo total de ejecución: " . gmdate("H:i:s", $duration));

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
}
