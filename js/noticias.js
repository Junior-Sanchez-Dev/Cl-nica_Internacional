document.addEventListener('DOMContentLoaded', function () {
  const articles = {
    tecnologia: ['Tecnología para la atención', '8 de marzo, 2026', 'images/news-image1.jpg', 'La tecnología permite reunir información que antes debía consultarse en diferentes pantallas.', 'Servicios conectados para simplificar cada paso.', 'La propuesta utiliza servicios de identidad, sedes y citas para organizar la solicitud de atención desde un único portal. Consulta las evidencias técnicas para conocer el alcance de cada integración.'],
    atencion: ['Un nuevo proceso de atención', '20 de febrero, 2026', 'images/news-image2.jpg', 'El portal organiza la reserva en pasos claros: paciente, cobertura, sede y horario, y confirmación.', 'Revisa tus datos y la disponibilidad antes de confirmar.', 'Después de iniciar sesión, puedes elegir la especialidad y el horario disponible. La reserva confirmada se guarda y puede consultarse en Mis citas.'],
    investigacion: ['Revisión anual de investigación médica', '27 de enero, 2026', 'images/news-image3.jpg', 'La revisión de los procesos permite identificar oportunidades para organizar mejor la atención y el acceso a la información.', 'Una atención organizada comienza con información disponible en cada etapa.', 'En esta propuesta académica, los servicios conectan la consulta de identidad, cobertura, ubicación y reserva. Se estudia su integración para mejorar el seguimiento del proceso.']
  };
  const a=articles[new URLSearchParams(location.search).get('articulo')] || articles.investigacion;
  document.title=a[0]+' | Clínica Internacional';
  ['article-title','article-date','article-intro','article-quote','article-body'].forEach((id,i)=>document.getElementById(id).textContent=a[[0,1,3,4,5][i]]);
  const image=document.querySelector('.news-detail-thumb .news-image img');image.src=a[2];image.alt=a[0];
});
