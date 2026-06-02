{{--
    Componente de toast notifications.

    `showToast(message, type)` ahora es GLOBAL y unificado: se define en
    resources/js/bootstrap.js (→ resources/js/utils/notifications.js) y usa toastr.
    Este componente se conserva por compatibilidad con las vistas que lo incluyen,
    pero ya no redefine showToast (antes creaba un toast DOM custom duplicado).
--}}
