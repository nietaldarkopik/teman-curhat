@if ($paginator->hasPages())
<nav aria-label="Navigasi halaman">
  <ul class="pagination">
    @if ($paginator->onFirstPage())
      <li class="disabled" aria-disabled="true"><span>&lsaquo; Sebelumnya</span></li>
    @else
      <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Sebelumnya</a></li>
    @endif

    @if ($paginator->hasMorePages())
      <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya &rsaquo;</a></li>
    @else
      <li class="disabled" aria-disabled="true"><span>Berikutnya &rsaquo;</span></li>
    @endif
  </ul>
</nav>
@endif
