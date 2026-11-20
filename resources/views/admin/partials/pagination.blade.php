{{-- Simple previous / next pagination for admin lists. --}}
@if ($paginator->hasPages())
  <nav class="adm-actions" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="adm-btn adm-btn-light adm-btn-sm" aria-disabled="true" style="opacity:.5">&larr; Previous</span>
    @else
      <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; Previous</a>
    @endif
    <span class="adm-hint">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ $paginator->total() }} total</span>
    @if ($paginator->hasMorePages())
      <a class="adm-btn adm-btn-light adm-btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rarr;</a>
    @else
      <span class="adm-btn adm-btn-light adm-btn-sm" aria-disabled="true" style="opacity:.5">Next &rarr;</span>
    @endif
  </nav>
@endif
