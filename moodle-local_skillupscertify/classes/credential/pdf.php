<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_skillupscertify\credential;

use local_skillupscertify\access;

/**
 * Generate a printable certificate using Moodle's PDF library.
 *
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf implements renderer {
    /**
     * Render an A4 certificate with Unicode and a verification QR.
     *
     * @param array $snapshot Issued design and recipient fields
     * @param string $verificationurl Verification URL, or empty for preview
     * @return string
     */
    public function render(array $snapshot, string $verificationurl): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $layout = $snapshot['design'];
        $fields = $snapshot['fields'];
        $pdf = new \pdf($layout['orientation'], 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator('SkillUps Certify & Verify');
        $pdf->SetTitle($layout['title']);
        $pdf->AddPage($layout['orientation']);
        $width = $pdf->getPageWidth();
        $height = $pdf->getPageHeight();
        $color = design::COLORS[$layout['color']];
        $pdf->SetDrawColor(...$color);
        $pdf->SetLineWidth(0.65);
        $pdf->Rect(8, 8, $width - 16, $height - 16);
        $pdf->SetFillColor(...$color);
        $pdf->Rect(8, 8, $width - 16, 4, 'F');
        $pdf->SetTextColor(...$color);
        $this->line($pdf, $layout['issuer'], 24, 11, 'B', 10);
        $this->line($pdf, $layout['title'], 45, 27, 'B', 30);
        $pdf->SetTextColor(34, 39, 50);
        $this->line($pdf, $fields['recipient'], 82, 22, 'B', 20);
        $this->line($pdf, design::expand($layout['body'], $fields), 108, 11, '', min(46, $height - 169));
        if ($layout['signer'] !== '') {
            $this->line($pdf, $layout['signer'], $height - 58, 10, 'B', 11);
        }
        $pdf->SetTextColor(...$color);
        if ($verificationurl !== '') {
            $pdf->write2DBarcode($verificationurl, 'QRCODE,M', $width - 49, $height - 46, 34, 34, [
                'border' => 0, 'padding' => 3, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255],
            ]);
        }
        $pdf->SetFont('freesans', '', 8);
        $pdf->SetXY(17, $height - 31);
        $pdf->Cell($width - 70, 6, $fields['serial'], 0, 1, 'L', false, $verificationurl);
        $pdf->SetX(17);
        $pdf->Cell($width - 70, 6, $fields['issuedate'], 0, 1, 'L');
        if (preg_match('/[?&]code=([a-f0-9]{48})(?:&|$)/D', $verificationurl, $match)) {
            $pdf->SetX(17);
            $pdf->Cell($width - 70, 6, access::text('code') . ' ' . $match[1], 0, 1, 'L', false, $verificationurl);
        }
        return $pdf->Output('certificate.pdf', 'S');
    }

    /**
     * Put bounded, escaped-by-TCPDF text in a centered area.
     *
     * @param \pdf $pdf PDF document
     * @param string $text Display text
     * @param float $top Y position
     * @param float $size Font size
     * @param string $style Typeface
     * @param float $height Maximum cell height
     */
    private function line(\pdf $pdf, string $text, float $top, float $size, string $style, float $height): void {
        $width = $pdf->getPageWidth() - 44;
        $pdf->SetFont('freesans', $style, $size);
        $pdf->MultiCell($width, $height, $text, 0, 'C', false, 1, 22, $top, true, 0, false, true, $height, 'M', true);
    }
}
