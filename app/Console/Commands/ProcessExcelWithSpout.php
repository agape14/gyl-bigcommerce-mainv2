<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

class ProcessExcelWithSpout extends Command
{
    protected $signature = 'excel:processspout:spout {path} {outputCsv}';
    protected $description = 'Process Excel to CSV with Spout';

    public function handle()
    {
        $startTime = now();
        $this->info('Proceso iniciado: ' . $startTime);

        $path = $this->argument('path');
        $outputCsv = $this->argument('outputCsv');

        // Establecer una carpeta temporal personalizada en tu proyecto
        $customTempFolder = storage_path('app/temp');

        // Crear la carpeta si no existe
        if (!file_exists($customTempFolder)) {
            mkdir($customTempFolder, 0777, true);
        }

        // Crear un lector para el archivo Excel
        $reader = ReaderEntityFactory::createXLSXReader();
        $reader->setTempFolder($customTempFolder);
        $reader->open($path);

        // Solo procesar la primera hoja (Sheet1)
        $sheetIterator = $reader->getSheetIterator();
        $sheetIterator->rewind(); // Mueve el iterador al inicio, primera hoja

        // Si hay hojas disponibles, procesar solo la primera
        if ($sheetIterator->valid()) {
            $firstSheet = $sheetIterator->current();  // Obtener la primera hoja (Sheet1)
            $rowIterator = $firstSheet->getRowIterator();

            // Crear un escritor para el archivo CSV
            $writer = WriterEntityFactory::createCSVWriter();
            $writer->openToFile($outputCsv);

            // Inicializar un contador de filas
            $rowCounter = 0;
            $headerRow = [];
            $headerjson = [];
            $jsonHeader = 'Attributes_JSON'; // Nueva cabecera para el campo JSON

            // Iterar sobre las filas de la primera hoja
            foreach ($rowIterator as $row) {
                $rowCounter++;
                $cells = $row->getCells();
                $rowData = [];

                // Extraer los valores de las celdas
                foreach ($cells as $cell) {
                    $cellValue = $cell->getValue();

                    // Eliminar comillas dobles dentro de los valores de celdas
                    if (is_string($cellValue)) {
                        $cellValue = str_replace('"', '', $cellValue);
                    }

                    $rowData[] = $cellValue;
                }

                // Obtener la primera fila como cabecera
                if ($rowCounter == 1) {
                    $headerjson = $rowData;
                    // Solo tomar las cabeceras de $block1 y $block3, agregar la cabecera JSON
                    $headerRow = array_merge(
                        array_slice($rowData, 0, 18),  // Primeras 18 columnas (block1)
                        array_slice($rowData, 519, 540), // Desde la columna 519 a la 540 (block3)
                        [$jsonHeader] // Cabecera del campo JSON
                    );

                    // Escribir las cabeceras en el archivo CSV
                    $writer->addRow(WriterEntityFactory::createRowFromArray($headerRow));
                    continue;
                }

                // Separar en bloques
                $block1 = array_slice($rowData, 0, 18);

                // Bloque 2: Columnas S hasta SX (Índice 19 a 518)
                $block2Array = array_slice($rowData, 19, 518);
                $block2 = [];

                // Usar las cabeceras correspondientes para el bloque 2
                $block2Headers = array_slice($headerjson, 19, 518);
                foreach ($block2Array as $key => $value) {
                    if (!empty($value)) {
                        $block2[$block2Headers[$key]] = $value; // Asignar nombre de cabecera a cada valor
                    }
                }

                // Convertir el bloque 2 a JSON sin escapar comillas innecesarias
                $block2Json = json_encode($block2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Bloque 3: Desde columna SY en adelante (Índice 519 hasta el total de columnas)
                $block3 = array_slice($rowData, 519, 540);

                // Combinar bloques y añadir el bloque JSON al final
                $combinedRow = array_merge($block1, $block3, [$block2Json]);

                // Escribir la fila combinada en el archivo CSV
                $writer->addRow(WriterEntityFactory::createRowFromArray($combinedRow));
            }

            // Cerrar los recursos
            $writer->close();
        }

        // Cerrar el lector
        $reader->close();

        // Limpia la carpeta temporal después de finalizar el procesamiento
        $this->cleanupTempFolder($customTempFolder);

        $endTime = now();
        $this->info('Proceso finalizado: ' . $endTime);
        $this->info('Duración total: ' . $startTime->diffInMinutes($endTime) . ' minutos');
    }

    private function cleanupTempFolder($folderPath)
    {
        // Elimina todos los archivos dentro de la carpeta temporal
        $files = glob($folderPath . '/*'); // Obtener todos los archivos en la carpeta

        foreach ($files as $file) {
            if (is_file($file)) {
                try {
                    // Intentar eliminar el archivo
                    unlink($file);
                } catch (\Exception $e) {
                    // Mostrar un mensaje si no se puede eliminar el archivo
                    $this->error("No se pudo eliminar el archivo: $file. Error: " . $e->getMessage());
                }
            }
        }

        // Intentar eliminar la carpeta si está vacía
        try {
            rmdir($folderPath);
            $this->info("Carpeta temporal eliminada: $folderPath");
        } catch (\Exception $e) {
            // Mostrar un mensaje si no se puede eliminar la carpeta
            $this->error("No se pudo eliminar la carpeta: $folderPath. Error: " . $e->getMessage());
        }
    }
}
