class ChocanteProductFilters {
  constructor(formElement) {
    this.form = formElement;

    if (this.form) {
      this.form.addEventListener('submit', this.submitFilters.bind(this));
      this.form.addEventListener('reset', this.resetFilters.bind(this));
    }
  }

  async submitFilters(event) {
    event.preventDefault();

    const data = new FormData(event.target);
    const url = new URL(event.target.action);

    for (let filter of data.keys()) {
      const value = data.getAll(filter);

      if (!url.searchParams.has(filter)) {
        url.searchParams.append(filter, value);
      }
    }

    window.location.href = url;
  }

  resetFilters(event) {
    window.location.href = event.target.action;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const filters = document.querySelectorAll('.chocante-product-filters');

  if (Array.from(filters).length) {
    // Init filters.
    for (const filter of Array.from(filters)) {
      new ChocanteProductFilters(filter);
    }

    // Scroll to filters.
    const url = new URL(window.location.href);

    if (url.searchParams.size > 0) {
      window.scrollTo({
        top: filters[0].getBoundingClientRect().top - 50,
        behavior: "smooth"
      })
    }
  }
});