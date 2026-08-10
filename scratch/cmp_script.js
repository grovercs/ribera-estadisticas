let chartType = 'bar';

function setChartType(type) {
    chartType = type;
    salesChart.config.type = type;
    salesChart.update();
}

// Datos para gráfico de evolución mensual
const monthlyData = [{"year":"2025","month":"1","total":"1316320.660000"},{"year":"2025","month":"2","total":"1504444.480000"},{"year":"2025","month":"3","total":"1733369.700000"},{"year":"2025","month":"4","total":"1892702.740000"},{"year":"2025","month":"5","total":"1972018.050000"},{"year":"2025","month":"6","total":"1739390.620000"},{"year":"2025","month":"7","total":"2382589.720000"},{"year":"2025","month":"8","total":"1748574.260000"},{"year":"2025","month":"9","total":"2079719.520000"},{"year":"2025","month":"10","total":"2330587.310000"},{"year":"2025","month":"11","total":"1942772.270000"},{"year":"2025","month":"12","total":"1654821.790000"},{"year":"2026","month":"1","total":"1102423.620000"},{"year":"2026","month":"2","total":"2564976.280000"},{"year":"2026","month":"3","total":"2337498.350000"},{"year":"2026","month":"4","total":"2614209.560000"},{"year":"2026","month":"5","total":"2311368.610000"},{"year":"2026","month":"6","total":"1840915.140000"}];
const labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const dataYear1 = new Array(12).fill(0);
const dataYear2 = new Array(12).fill(0);

monthlyData.forEach(item => {
    const monthIndex = item.month - 1;
    if (item.year == 2025) {
        dataYear1[monthIndex] = parseFloat(item.total);
    } else if (item.year == 2026) {
        dataYear2[monthIndex] = parseFloat(item.total);
    }
});

// Gráfico de Ventas
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: '2025',
                data: dataYear1,
                backgroundColor: 'rgba(116, 120, 120, 0.7)',
                borderColor: '#747878',
                borderWidth: 1
            },
            {
                label: '2026',
                data: dataYear2,
                backgroundColor: 'rgba(32, 99, 147, 0.7)',
                borderColor: '#206393',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'k €';
                    }
                }
            },
            x: { grid: { display: false } }
        }
    }
});

// Gráfico de Familias (comparativa year1 vs year2)
const familyCtx = document.getElementById('familyChart').getContext('2d');
const familyData = [{"cod_familia":"1060","familia":"TEJADOS","y1_revenue":1278741.4,"y2_revenue":1129401.93,"growth":-11.678629471134663},{"cod_familia":"1150","familia":"PUERTAS Y ACCESORIOS","y1_revenue":1044536.6,"y2_revenue":1047274.01,"growth":0.2620693233726834},{"cod_familia":"1050","familia":"AISLANTES E IMPERMEABILIZANTES","y1_revenue":1123473.12,"y2_revenue":807312.6,"growth":-28.141351526060554},{"cod_familia":"2390","familia":"FERRETERIA VARIOS","y1_revenue":1253688.14,"y2_revenue":741893.48,"growth":-40.82312368369377},{"cod_familia":"1040","familia":"PLACA DE YESO LAMINADO Y DERIVADOS","y1_revenue":920442.81,"y2_revenue":660391.42,"growth":-28.25285690481954},{"cod_familia":"3000","familia":"CERAMICA Y GRES","y1_revenue":1275829.52,"y2_revenue":580200.5,"growth":-54.52366551292841},{"cod_familia":"4070","familia":"LINEA BLANCA - ELECTRODOMESTICOS","y1_revenue":1104797.49,"y2_revenue":531247.56,"growth":-51.91448525104813},{"cod_familia":"6010","familia":"CODIGOS GENERICOS","y1_revenue":587182.93,"y2_revenue":465829.58,"growth":-20.667043233017694},{"cod_familia":"1070","familia":"VELUX - VENTANAS. CERCOS Y ACCESORIOS","y1_revenue":1016953.99,"y2_revenue":444388.79,"growth":-56.30197684754646},{"cod_familia":"1010","familia":"MATERIAL DE OBRA (OBRA BASTA)","y1_revenue":812167.52,"y2_revenue":394634.38,"growth":-51.4097313322749},{"cod_familia":"1020","familia":"MATERIALES EN SACOS","y1_revenue":673174.69,"y2_revenue":361962.82,"growth":-46.23047696579323},{"cod_familia":"2450","familia":"PARQUETS","y1_revenue":833338.59,"y2_revenue":356161.65,"growth":-57.26087159842195},{"cod_familia":"1100","familia":"FORJADOS Y ENCOFRADOS","y1_revenue":447656.14,"y2_revenue":302792.62,"growth":-32.360445229233314},{"cod_familia":"3050","familia":"GRIFERIA","y1_revenue":713640.1,"y2_revenue":296076.81,"growth":-58.51174702766843},{"cod_familia":"2320","familia":"FUEGOS, ESTUFAS Y CALDERAS","y1_revenue":524545.52,"y2_revenue":273665.22,"growth":-47.82812748071893}];
const year1Lbl = '2025', year2Lbl = '2026';

// Color de la barra de year2 según crecimiento (verde=sube, rojo=baja, gris=sin año previo)
const familyColorsY2 = familyData.map(f => {
    if (f.y1_revenue === 0 && f.y2_revenue > 0) return 'rgba(116,120,120,0.6)';
    if (f.growth >= 0) return 'rgba(40,167,69,0.85)';   // crece → verde
    return 'rgba(220,53,69,0.8)';                        // cae → rojo
});

function familyDatasets(rows) {
    return [
        {
            label: 'Facturación ' + year1Lbl,
            data: rows.map(f => f.y1_revenue),
            backgroundColor: 'rgba(116,120,120,0.35)',
            borderColor: '#747878',
            borderWidth: 1,
            borderRadius: 3
        },
        {
            label: 'Facturación ' + year2Lbl,
            data: rows.map(f => f.y2_revenue),
            backgroundColor: familyColorsY2,
            borderColor: '#206393',
            borderWidth: 1,
            borderRadius: 3
        }
    ];
}

const familyChart = new Chart(familyCtx, {
    type: 'bar',
    data: {
        labels: familyData.map(f => (f.familia || 'N/A').substring(0, 25)),
        datasets: familyDatasets(familyData)
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.dataset.label + ': ' + ctx.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
                    },
                    afterBody: function(ctxArr) {
                        const f = familyData[ctxArr[0].dataIndex];
                        if (!f) return '';
                        const sign = f.growth >= 0 ? '+' : '';
                        const lines = ['Δ ' + year1Lbl + '→' + year2Lbl + ': ' + sign + f.growth.toFixed(1) + '%'];
                        if (f.y1_revenue === 0 && f.y2_revenue > 0) lines.push('(sin ventas en ' + year1Lbl + ')');
                        return lines;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { callback: v => (v / 1000) + 'k €' }
            },
            y: { grid: { display: false } }
        }
    }
});

// Toggle de ordenación: facturación year2 ↔ crecimiento
const famSortBtn = document.getElementById('famSortToggle');
if (famSortBtn) {
    famSortBtn.addEventListener('click', () => {
        const byGrowth = famSortBtn.dataset.mode === 'revenue';
        const sorted = [...familyData].sort((a, b) => byGrowth ? b.growth - a.growth : b.y2_revenue - a.y2_revenue);
        familyChart.data.labels = sorted.map(f => (f.familia || 'N/A').substring(0, 25));
        familyChart.data.datasets[0].data = sorted.map(f => f.y1_revenue);
        // recalcula colores en el nuevo orden
        familyChart.data.datasets[1].data = sorted.map(f => f.y2_revenue);
        familyChart.data.datasets[1].backgroundColor = sorted.map(f => {
            if (f.y1_revenue === 0 && f.y2_revenue > 0) return 'rgba(116,120,120,0.6)';
            if (f.growth >= 0) return 'rgba(40,167,69,0.85)';
            return 'rgba(220,53,69,0.8)';
        });
        // familyData se usa en el tooltip por dataIndex → reordenamos también la referencia
        familyData.sort((a, b) => byGrowth ? b.growth - a.growth : b.y2_revenue - a.y2_revenue);
        famSortBtn.dataset.mode = byGrowth ? 'growth' : 'revenue';
        famSortBtn.textContent = 'Ordenar: ' + (byGrowth ? 'por crecimiento' : 'por facturación');
        familyChart.update();
    });
}

// Gráfico de Productos
const productCtx = document.getElementById('productChart').getContext('2d');
const productData = [{"cod_articulo":"ALMACEN","descripcion":"Vigueta Pretensada VP-18 de L-3,20m","familia":"PUERTAS Y ACCESORIOS","year1_revenue":915146.55,"year2_revenue":941486.96,"year1_qty":69828.9117,"year2_qty":44182.548,"year1_orders":409,"year2_orders":187,"growth":2.878272337911334,"year1_avg_ticket":2237.5221271393643,"year2_avg_ticket":5034.689625668449},{"cod_articulo":"BRICO","descripcion":"ZOCALO PARECIDO A MANTARO","familia":"FERRETERIA VARIOS","year1_revenue":1169256.42,"year2_revenue":707391.58,"year1_qty":13256.5626,"year2_qty":19320.17,"year1_orders":444,"year2_orders":370,"growth":-39.50073158460828,"year1_avg_ticket":2633.4604054054053,"year2_avg_ticket":1911.869135135135},{"cod_articulo":"7890","descripcion":"M2 Pizarra CUPA 5 EXCELLENCE 32X22  (36p\\m2)","familia":"TEJADOS","year1_revenue":269119.28,"year2_revenue":318082.61,"year1_qty":6456.87,"year2_qty":6183.18,"year1_orders":25,"year2_orders":17,"growth":18.19391386600022,"year1_avg_ticket":10764.771200000001,"year2_avg_ticket":18710.741764705883},{"cod_articulo":"75007881","descripcion":"M2. Pizarra CUPA 5 ARD. 20x30 (43p\/m2 \u00fatil)","familia":"TEJADOS","year1_revenue":341001.4,"year2_revenue":283008.17,"year1_qty":10684.855449,"year2_qty":8832.66437,"year1_orders":314,"year2_orders":138,"growth":-17.006742494312352,"year1_avg_ticket":1085.991719745223,"year2_avg_ticket":2050.78384057971},{"cod_articulo":"MARMOLES","descripcion":"SUMISNISTRO E INSTALACI\u00d3N ENCIMERA DE COCINA Y FRONTAL EN GRANITO NEGRO ZIMBAWE MATE","familia":"PIEDRAS NATURALES","year1_revenue":243933.18,"year2_revenue":257350.86,"year1_qty":91.62,"year2_qty":456.04,"year1_orders":44,"year2_orders":55,"growth":5.5005555209832435,"year1_avg_ticket":5543.935909090909,"year2_avg_ticket":4679.106545454545},{"cod_articulo":"MUEBLES","descripcion":"SALGAR SPIRIT MUEBLES SUSP. 2 CAJONES 800*540*450 mm. MOON GREY  119429","familia":"CODIGOS GENERICOS","year1_revenue":427709.66,"year2_revenue":243364.42,"year1_qty":224,"year2_qty":123,"year1_orders":69,"year2_orders":40,"growth":-43.100555643283805,"year1_avg_ticket":6198.69072463768,"year2_avg_ticket":6084.110500000001},{"cod_articulo":"60138486","descripcion":"M2 ORIGINALS FOREST UNFINISHED LOOK 190x1900x14mm ACEITADO ( 2,88m2\/Cj )","familia":"PARQUETS","year1_revenue":90615.54,"year2_revenue":129605.2,"year1_qty":1576.608,"year2_qty":2254.984,"year1_orders":7,"year2_orders":5,"growth":43.02756458770759,"year1_avg_ticket":12945.077142857142,"year2_avg_ticket":25921.04},{"cod_articulo":"S00","descripcion":"TRABAJOS VARIOS DE ELECTRICIDAD, A\u00d1ADIR TOMA PARA MICRO Y HORNO EN COLUMNA, PUNTO LUZ PARA LED MUEBLE ALTO.","familia":"SERVICIOS","year1_revenue":90061.44,"year2_revenue":122319.61,"year1_qty":321,"year2_qty":576.8,"year1_orders":327,"year2_orders":167,"growth":35.817959384171516,"year1_avg_ticket":275.417247706422,"year2_avg_ticket":732.4527544910179},{"cod_articulo":"60135386","descripcion":"M2 PIZARRA PEDRAVAL CUBIERTA 32X22X6 (36P\/M2)","familia":"TEJADOS","year1_revenue":0,"year2_revenue":120286.37,"year1_qty":0,"year2_qty":4123.04,"year1_orders":0,"year2_orders":41,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":2933.8139024390243},{"cod_articulo":"1102395","descripcion":"PLACA PLADUR N ESTANDAR 13x1200x2600 (42 pl\/pt)","familia":"PLACA DE YESO LAMINADO Y DERIVADOS","year1_revenue":0,"year2_revenue":120218.62,"year1_qty":0,"year2_qty":9360,"year1_orders":0,"year2_orders":147,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":817.8137414965986},{"cod_articulo":"AZULEJO","descripcion":"WC TURA ADOSADO PARED","familia":"CODIGOS GENERICOS","year1_revenue":0,"year2_revenue":119198.29,"year1_qty":0,"year2_qty":2888.74,"year1_orders":0,"year2_orders":18,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":6622.1272222222215},{"cod_articulo":"60086863","descripcion":"M2 PIZARRA CUPA 2 EXCELLENCE 30X20 (43pz\/m2)","familia":"TEJADOS","year1_revenue":103785.12,"year2_revenue":112803.2,"year1_qty":2814.966,"year2_qty":2824.51,"year1_orders":19,"year2_orders":23,"growth":8.68918396008985,"year1_avg_ticket":5462.374736842105,"year2_avg_ticket":4904.486956521739},{"cod_articulo":"5000","descripcion":"M2.ADOQ.GRANITO 20x10x10(6.82m2\/s) 44p\/m2","familia":"GRANITOS, PIEDRAS Y PIZARRAS","year1_revenue":0,"year2_revenue":92021.17,"year1_qty":0,"year2_qty":2641.458,"year1_orders":0,"year2_orders":57,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":1614.40649122807},{"cod_articulo":"SANITARIOS","descripcion":"VIDREBANY DESAG\u00dcE BA\u00d1ERA CROMO \/ MINERAL A ELEGIR","familia":"CODIGOS GENERICOS","year1_revenue":0,"year2_revenue":87499.62,"year1_qty":0,"year2_qty":385,"year1_orders":0,"year2_orders":12,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":7291.634999999999},{"cod_articulo":"1498H","descripcion":"Saco Cemento BIGMAT II 32,5 R 25 Kg\/Saco (56 s\/p)","familia":"MATERIALES EN SACOS","year1_revenue":159430.62,"year2_revenue":85497.56,"year1_qty":30860,"year2_qty":16579,"year1_orders":1836,"year2_orders":839,"growth":-46.37318728359709,"year1_avg_ticket":86.83584967320262,"year2_avg_ticket":101.90412395709177},{"cod_articulo":"10121","descripcion":"M2 URSA TERRA 46R 1170X40X46mm (14,04 m2\/pq)(24 pq\/pt)(336,96 m2\/pt)","familia":"AISLANTES E IMPERMEABILIZANTES","year1_revenue":93724.87,"year2_revenue":68394.39,"year1_qty":20320.69,"year2_qty":14269.92,"year1_orders":228,"year2_orders":131,"growth":-27.026423189490682,"year1_avg_ticket":411.07399122807016,"year2_avg_ticket":522.0945801526717},{"cod_articulo":"MAMPARAS","descripcion":"SANYCCES MAMPARA DE DUCHA SINGLE CUSTOM 120CM H:200CM CRISTAL TRANSPARENTE PERFIL NIKEL 2 FIJOS LATERALES + PUERTA ABATIBLE 90\u00ba EXTERIOR 6CM CENTRAL","familia":"MAMPARAS","year1_revenue":129461.76,"year2_revenue":63970.05,"year1_qty":167,"year2_qty":65,"year1_orders":65,"year2_orders":22,"growth":-50.58768705137331,"year1_avg_ticket":1991.7193846153846,"year2_avg_ticket":2907.7295454545456},{"cod_articulo":"60142728","descripcion":"M2 Pizarra CUPA 30X20 H12 (43pz\/m2)","familia":"TEJADOS","year1_revenue":0,"year2_revenue":63568.42,"year1_qty":0,"year2_qty":2359,"year1_orders":0,"year2_orders":23,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":2763.844347826087},{"cod_articulo":"1102391","descripcion":"PLACA PLADUR N ESTANDAR 15x1200x2600 (36 pl\/pt)","familia":"PLACA DE YESO LAMINADO Y DERIVADOS","year1_revenue":126391.24,"year2_revenue":58947.69,"year1_qty":8549,"year2_qty":3817,"year1_orders":109,"year2_orders":93,"growth":-53.36093703962395,"year1_avg_ticket":1159.5526605504588,"year2_avg_ticket":633.8461290322581},{"cod_articulo":"58616","descripcion":"M2 ALPHAROCK PREMIUM E-225 135X40X4cm (5,40m2\/paq) (97,20m2\/pt) R.337090","familia":"AISLANTES E IMPERMEABILIZANTES","year1_revenue":0,"year2_revenue":57755.82,"year1_qty":0,"year2_qty":6369.34,"year1_orders":0,"year2_orders":48,"growth":100,"year1_avg_ticket":0,"year2_avg_ticket":1203.24625}];
const top10Products = productData.slice(0, 10);

new Chart(productCtx, {
    type: 'bar',
    data: {
        labels: top10Products.map(p => p.cod_articulo),
        datasets: [
            {
                label: '2025',
                data: top10Products.map(p => parseFloat(p.year1_revenue || 0)),
                backgroundColor: 'rgba(116, 120, 120, 0.7)',
                borderColor: '#747878',
                borderWidth: 1
            },
            {
                label: '2026',
                data: top10Products.map(p => parseFloat(p.year2_revenue || 0)),
                backgroundColor: 'rgba(32, 99, 147, 0.7)',
                borderColor: '#206393',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
                    },
                    title: function(context) {
                        const idx = context[0].dataIndex;
                        return top10Products[idx].descripcion?.substring(0, 50);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'k €';
                    }
                }
            },
            x: { grid: { display: false } }
        }
    }
});
