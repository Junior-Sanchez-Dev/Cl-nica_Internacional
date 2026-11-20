// Todas las solicitudes de servicios pasan por Apache Synapse. Sin acceso alternativo.
window.ClinicaESB = Object.freeze({
  url(path) {
    const [file, query = ''] = path.replace(/^php\//, '').split('?');
    if (!/^[a-z_]+\.php$/.test(file)) throw Error('Ruta de servicio no valida.');
    const params = new URLSearchParams(query);
    params.set('servicio', file.slice(0, -4));
    return 'php/esb.php?' + params.toString();
  }
});
