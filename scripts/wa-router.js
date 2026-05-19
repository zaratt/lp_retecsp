(function () {
  var ATENDENTES = [
    { num: "5511993600810", nome: "Atendente 1" },
    { num: "5511940309983", nome: "Atendente 2" },
    { num: "5511982875060", nome: "Atendente 3" }
  ];

  var KEY = "retec_wa_atendente_teste";
  var idx = parseInt(localStorage.getItem(KEY), 10);

  if (isNaN(idx) || idx < 0 || idx >= ATENDENTES.length) {
    idx = Math.floor(Math.random() * ATENDENTES.length);
    localStorage.setItem(KEY, String(idx));
  }

  var atendente = ATENDENTES[idx];
  var links = document.querySelectorAll('a[data-wa-rotate="comercial"]');

  links.forEach(function (a) {
    a.href = a.href.replace(/wa\.me\/\d+/, "wa.me/" + atendente.num);
  });

  window.retecWaTeste = {
    indice: idx,
    numero: atendente.num,
    nome: atendente.nome
  };
})();
