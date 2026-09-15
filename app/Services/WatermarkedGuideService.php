<?php

declare(strict_types=1);

namespace GB\Services;

use RuntimeException;
use setasign\Fpdi\Fpdi;

/** Genera una copia PDF marcada sin exponer el archivo original protegido. */
final class WatermarkedGuideService
{
    public function render(string $source, string $studentLabel): string
    {
        if (!class_exists(Fpdi::class)) {
            throw new RuntimeException('La entrega de guías requiere instalar las dependencias PDF del proyecto.');
        }

        $pdf = new Fpdi();
        $pages = $pdf->setSourceFile($source);
        $watermark = mb_substr($studentLabel, 0, 190);

        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(110, 110, 110);
            $pdf->SetXY(10, max(10, $size['height'] - 12));
            $pdf->Cell(0, 5, 'Copia personal de: ' . $watermark, 0, 0, 'L');
        }

        return $pdf->Output('S');
    }
}