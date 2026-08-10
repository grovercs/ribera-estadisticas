<?php

namespace App\Services;

use MathPHP\Statistics\Average;
use MathPHP\Statistics\Correlation;
use MathPHP\Statistics\Descriptive;
use MathPHP\Statistics\Regression\Linear;

class StatisticsService
{
    /**
     * Regresión lineal simple sobre una serie temporal indexada por año.
     *
     * @param array<int, float> $values  year => value
     *
     * @return array{slope: float, intercept: float, r2: float, trend_line: array<int, float>}
     */
    public function linearRegression(array $values): array
    {
        $years = array_keys($values);
        $totals = array_values($values);
        $n = count($values);

        if ($n < 2) {
            return [
                'slope' => 0.0,
                'intercept' => $totals[0] ?? 0.0,
                'r2' => 0.0,
                'trend_line' => $values,
            ];
        }

        // Normalizar años a 0,1,2... para evitar números grandes en la regresión.
        $xs = range(0, $n - 1);
        $points = [];
        foreach ($xs as $i => $x) {
            $points[] = [$x, $totals[$i]];
        }

        try {
            $regression = new Linear($points);
            $regression->calculate();
            $parameters = $regression->getParameters();
            $slope = (float) $parameters['m'];
            $intercept = (float) $parameters['b'];

            $r2 = 0.0;
            if ($n >= 3) {
                try {
                    $r2 = Correlation::r2($xs, $totals);
                } catch (\Throwable) {
                    $r2 = 0.0;
                }
            }

            $trendLine = [];
            foreach ($xs as $i => $x) {
                $trendLine[$years[$i]] = $slope * $x + $intercept;
            }

            return [
                'slope' => $slope,
                'intercept' => $intercept,
                'r2' => $r2,
                'trend_line' => $trendLine,
            ];
        } catch (\Throwable $e) {
            return [
                'slope' => 0.0,
                'intercept' => Average::mean($totals),
                'r2' => 0.0,
                'trend_line' => $values,
            ];
        }
    }

    /**
     * Tasa de crecimiento anual compuesto (CAGR).
     *
     * @param float $startValue valor inicial
     * @param float $endValue  valor final
     * @param int   $periods   número de periodos (años)
     *
     * @return float CAGR en porcentaje (0.08 = 8 %)
     */
    public function cagr(float $startValue, float $endValue, int $periods): float
    {
        if ($periods <= 0 || $startValue <= 0) {
            return 0.0;
        }

        return (float) (pow($endValue / $startValue, 1 / $periods) - 1) * 100;
    }

    /**
     * Coeficiente de variación (desviación típica / media).
     * Útil para medir la volatilidad relativa de ventas año a año.
     *
     * @param float[] $values
     *
     * @return float porcentaje
     */
    public function coefficientOfVariation(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        try {
            $mean = Average::mean($values);
            if ($mean == 0) {
                return 0.0;
            }

            $stdDev = Descriptive::standardDeviation($values, Descriptive::SAMPLE);

            return (float) ($stdDev / abs($mean)) * 100;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Índice estacional por mes a partir de una matriz [año][mes] = total.
     *
     * @param array<int, array<int, float>> $monthlyByYear
     *
     * @return array<int, float> mes => índice estacional (% sobre la media mensual)
     */
    public function seasonalityIndex(array $monthlyByYear): array
    {
        $monthTotals = array_fill(1, 12, 0.0);
        $monthCounts = array_fill(1, 12, 0);

        foreach ($monthlyByYear as $year => $months) {
            foreach ($months as $month => $total) {
                if ($month >= 1 && $month <= 12) {
                    $monthTotals[$month] += $total;
                    $monthCounts[$month]++;
                }
            }
        }

        $monthAverages = [];
        foreach ($monthTotals as $month => $total) {
            $monthAverages[$month] = $monthCounts[$month] > 0 ? $total / $monthCounts[$month] : 0.0;
        }

        $globalAverage = Average::mean(array_values($monthAverages)) ?: 1.0;

        $indices = [];
        foreach ($monthAverages as $month => $avg) {
            $indices[$month] = $globalAverage > 0 ? ($avg / $globalAverage) * 100 : 0.0;
        }

        return $indices;
    }

    /**
     * Proyección lineal para los próximos N periodos a partir de una serie histórica.
     *
     * @param array<int, float> $values  year => value
     * @param int               $periods número de años a proyectar
     *
     * @return array<int, float> año proyectado => valor estimado
     */
    public function forecast(array $values, int $periods): array
    {
        $years = array_keys($values);
        $n = count($values);

        if ($n < 2) {
            $lastYear = end($years) ?: date('Y');
            $lastValue = end($values) ?: 0.0;
            $forecast = [];
            for ($i = 1; $i <= $periods; $i++) {
                $forecast[$lastYear + $i] = $lastValue;
            }
            return $forecast;
        }

        $regression = $this->linearRegression($values);
        $lastX = $n - 1;

        $forecast = [];
        $lastYear = end($years);
        for ($i = 1; $i <= $periods; $i++) {
            $x = $lastX + $i;
            $forecast[$lastYear + $i] = $regression['slope'] * $x + $regression['intercept'];
        }

        return $forecast;
    }

    /**
     * Detecta outliers usando z-score.
     *
     * @param array<int, float> $values  year => value
     * @param float             $threshold umbral de z-score (por defecto 2.0)
     *
     * @return array<int, array{value: float, z: float, direction: string}>
     */
    public function zScoreOutliers(array $values, float $threshold = 2.0): array
    {
        if (count($values) < 3) {
            return [];
        }

        try {
            $mean = Average::mean(array_values($values));
            $stdDev = Descriptive::standardDeviation(array_values($values), Descriptive::SAMPLE);

            if ($stdDev == 0) {
                return [];
            }

            $outliers = [];
            foreach ($values as $year => $value) {
                $z = ($value - $mean) / $stdDev;
                if (abs($z) >= $threshold) {
                    $outliers[$year] = [
                        'value' => $value,
                        'z' => $z,
                        'direction' => $z > 0 ? 'up' : 'down',
                    ];
                }
            }

            return $outliers;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Calcula el crecimiento año a año para una serie temporal.
     *
     * @param array<int, float> $values year => value
     *
     * @return array<int, float> año => crecimiento % respecto al año anterior
     */
    public function yearOverYearGrowth(array $values): array
    {
        $growth = [];
        $previous = null;
        foreach ($values as $year => $value) {
            if ($previous === null || $previous == 0) {
                $growth[$year] = 0.0;
            } else {
                $growth[$year] = (($value - $previous) / $previous) * 100;
            }
            $previous = $value;
        }

        return $growth;
    }

    /**
     * Correlación de Pearson entre dos series.
     *
     * @param float[] $x
     * @param float[] $y
     *
     * @return float|null
     */
    public function correlation(array $x, array $y): ?float
    {
        if (count($x) < 2 || count($x) !== count($y)) {
            return null;
        }

        try {
            return Correlation::pearson($x, $y);
        } catch (\Throwable) {
            return null;
        }
    }
}
