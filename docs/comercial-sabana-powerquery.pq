// Crear una consulta en blanco en Excel y pegar en Editor avanzado.
// Crear antes el parametro de texto TokenSAEP con la credencial autorizada.
// Origen Web: autenticacion Anonima (la API valida la cabecera Bearer).
let
    Cliente = "", // Vacio: todos los clientes autorizados por esta credencial global.
    Centro = "",  // Nombre exacto; vacio: todos los centros.
    Vista = "vigentes", // "referencias" incluye precios pendientes e historicos.
    Leer = (Pagina as number, Fecha as nullable text) as record =>
        Json.Document(Web.Contents("https://saep.bmachero.com", [
            RelativePath = "comercial/api/sabana-tarifas",
            Query = Record.Combine({[vista=Vista, per_page="500", page=Text.From(Pagina), cliente=Cliente, centro=Centro],
                if Fecha = null then [] else [fecha=Fecha]}),
            Headers = [Authorization="Bearer " & TokenSAEP, Accept="application/json"],
            IsRetry = true,
            Timeout = #duration(0,0,2,0)
        ])),
    Primera = Leer(1, null),
    Otras = if Primera[pagination][last_page] > 1 then
        List.Transform({2..Primera[pagination][last_page]}, each Leer(_, Primera[fecha_consulta])[data]) else {},
    Filas = List.Combine({Primera[data]} & Otras),
    Columnas = {"tarifa_id","cliente","centro","cargo","categoria","modalidad","unidad","condiciones","moneda","precio",
        "estado_revision","vigente_en_fecha","fecha_referencia","vigente_desde","vigente_hasta","cotizacion_id",
        "cotizacion_numero","estado_cotizacion","actualizado_en"},
    Tabla = Table.FromRecords(Filas, Columnas, MissingField.UseNull),
    Tipos = Table.TransformColumnTypes(Tabla, {{"tarifa_id",Int64.Type},{"cotizacion_id",Int64.Type},
        {"precio",type number},{"vigente_en_fecha",type logical},{"fecha_referencia",type date},
        {"vigente_desde",type date},{"vigente_hasta",type date},{"actualizado_en",type datetimezone}}, "en-US"),
    Consulta = Table.AddColumn(Tipos,"consultado_en", each DateTimeZone.FromText(Primera[generated_at]), type datetimezone)
in
    Consulta
