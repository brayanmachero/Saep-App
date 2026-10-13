import unittest
from test_extraer_matrices import matrix
from extraer_formatos_cliente import polar, parallel_tables
from extraer_tablas import extract_table


class ClientFormatTests(unittest.TestCase):
    def test_existing_table_reader_selects_sale_price_when_present(self):
        cells = {'A1':'Cargo','B1':'Sueldo Base Mensual','C1':'Costo Final Mes','D1':'PRECIO DE VENTA MES',
                 'A2':'OPERARIO','B2':500000,'C2':700000,'D2':800000}
        results, _ = extract_table(matrix(cells),matrix(cells),'test')
        self.assertEqual([800000],[x['precio'] for x in results])
        self.assertEqual('mes',results[0]['unidad'])

    def test_polar_final_value_not_fee_or_cost_and_no_inherited_date(self):
        cells = {'A2':'OPERARIO','A3':'ESTRUCTURA DE SUELDO','C3':'VALOR',
                 'A4':'SUELDO BASE','C4':500000,'A31':'TOTAL COSTO EMPRESA','C31':700000,
                 'A33':'MARGEN','B33':0.15,'C33':105000,'A34':'TOTAL NETO CON FEE MENSUAL','C34':805000,
                 'A36':'TOTAL A FACTURAR  FINAL','C36':810000}
        result = polar(matrix(cells), matrix(cells|{'C36':'=C34+5000'}), '2026')
        self.assertEqual(1,len(result))
        self.assertEqual((810000,'C36','mes',None),tuple(result[0][k] for k in ('precio','celda','unidad','fecha_referencia')))
        self.assertEqual('=C34+5000',result[0]['conceptos'][-1]['formula'])
        self.assertEqual([],polar(matrix(cells|{'C4':'#REF!'}),matrix(cells),'2026'))

    def test_parallel_prices_keep_original_cells_and_skip_intermediate_cost(self):
        cells = {'A1':'Cargo','B1':'Sueldo Base Mensual','C1':'Costo Final Mes','D1':'PRECIO DE VENTA MES',
                 'F1':'Cargo','G1':'Sueldo Base Mensual','H1':'Costo Final Mes','I1':'Oferta Valor Hora',
                 'A2':'OPERARIO','B2':500000,'C2':700000,'D2':800000,
                 'F2':'OPERARIO','G2':550000,'H2':900000,'I2':5000}
        result = parallel_tables(matrix(cells,9),matrix(cells,9),'2026')
        self.assertEqual(['D2','H2','I2'],[r['celda'] for r in result])
        self.assertEqual([800000,900000,5000],[r['precio'] for r in result])
        self.assertEqual(['mes','mes','hora'],[r['unidad'] for r in result])
        self.assertEqual('F2',result[1]['conceptos'][0]['celda'])
        self.assertEqual('F2',result[2]['conceptos'][0]['celda'])
        self.assertTrue(all(r['fecha_referencia'] is None for r in result))
        self.assertNotEqual(result[0]['variante'],result[1]['variante'])


if __name__ == '__main__':
    unittest.main()
