<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Island;
use App\Models\Municipality;

class GeoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CREAR ISLAS MANUALMENTE
        $islandsData = [
            'ES703' => 'El Hierro',
            'ES704' => 'Fuerteventura',
            'ES705' => 'Gran Canaria',
            'ES706' => 'La Gomera',
            'ES707' => 'La Palma',
            'ES708' => 'Lanzarote',
            'ES709' => 'Tenerife'
        ];

        $islandMap = []; 

        foreach ($islandsData as $code => $name) {
            // updateOrCreate evita duplicados si ejecutas el seeder dos veces
            $island = Island::updateOrCreate(
                ['code' => $code], // Busca por código
                ['name' => $name]  // Si no existe, crea con este nombre
            );
            $islandMap[$code] = $island->id;
        }
        
        $this->command->info('Islas revisadas correctamente.');

        // 2. IMPORTAR MUNICIPIOS DESDE CSV
        // Asegúrate de que el archivo está en database/data/municipios.csv
        $csvFile = base_path('database/data/municipios.csv');

        if (!file_exists($csvFile)) {
            $this->command->error('No se encuentra el fichero: ' . $csvFile);
            return;
        }

        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            // Saltamos la cabecera. IMPORTANTE: El delimitador ahora es coma ","
            fgetcsv($handle, 1000, ","); 

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Verificamos que la línea tenga suficientes columnas para evitar errores
                // Según tu captura, la isla es la columna 6 (índice 6)
                if (count($data) < 7) {
                    continue; 
                }

                $municipalityCode = $data[0]; // 35001
                $municipalityName = $data[2]; // Agaete
                $islandCode = $data[6];       // ES705

                if (isset($islandMap[$islandCode])) {
                    Municipality::updateOrCreate(
                        ['code' => $municipalityCode],
                        [
                            'name' => $municipalityName,
                            'island_id' => $islandMap[$islandCode]
                        ]
                    );
                }
            }
            fclose($handle);
            $this->command->info('Municipios importados correctamente.');
        }
    }
}