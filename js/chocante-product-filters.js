class ChocanteProductFilters {
	static PRODUCT_FILTERS = "chocante-product-filters";
	static PARAM_FITLER = "filter_";
	static PARAM_RESET = "reset_filters";
	static SHOP_LOOP = "#shop";

	constructor(formElement) {
		this.form = formElement;

		if (this.form) {
			this.form.addEventListener("submit", this.submitFilters.bind(this));
			this.form.addEventListener("reset", this.resetFilters.bind(this));
		}

		this.scrollToFilters();
	}

	async submitFilters(event) {
		event.preventDefault();

		const data = new FormData(event.target);

		if (!Array.from(data.entries.length)) return;

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
		const url = new URL(event.target.action);
		const currentUrl = new URL(window.location.href);

		currentUrl.searchParams.forEach((value, key) => {
			if (!key.includes(ChocanteProductFilters.PARAM_FITLER)) {
				url.searchParams.append(key, value);
			}
		});

		url.searchParams.append(ChocanteProductFilters.PARAM_RESET, true);

		window.location.href = url;
	}

	scrollToFilters() {
		const url = new URL(window.location.href);

		for (let filter of url.searchParams.keys()) {
			const hasFilters = filter.includes(ChocanteProductFilters.PARAM_FITLER);
			const hasReset = ChocanteProductFilters.PARAM_RESET === filter;

			if (hasFilters || hasReset) {
				const filters = document.querySelector(
					ChocanteProductFilters.SHOP_LOOP,
				);

				if (filters) {
					window.requestAnimationFrame(() => {
						filters.scrollIntoView();

						if(hasReset) {
							url.searchParams.delete(ChocanteProductFilters.PARAM_RESET);
							history.replaceState({}, '', url);
						}
					});
				}
			}
		}
	}
}

document.addEventListener("DOMContentLoaded", () => {
	const filters = document.getElementById(
		ChocanteProductFilters.PRODUCT_FILTERS,
	);

	if (filters) {
		new ChocanteProductFilters(filters);
	}
});
