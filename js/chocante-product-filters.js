class ChocanteProductFilters {
	static PRODUCT_FILTERS = 'chocante-product-filters';
	static SHOP_LOOP = '#shop'

	constructor(formElement) {
		this.form = formElement;

		if (this.form) {
			this.form.addEventListener("submit", this.submitFilters.bind(this));
			this.form.addEventListener("reset", this.resetFilters.bind(this));
		}
	}

	async submitFilters(event) {
		event.preventDefault();

		const data = new FormData(event.target);

		if(!Array.from(data.entries.length)) return;

		const url = new URL(event.target.action);

		for (let filter of data.keys()) {
			const value = data.getAll(filter);

			if (!url.searchParams.has(filter)) {
				url.searchParams.append(filter, value);
			}
		}

		this.reloadAndScroll(url);
	}

	resetFilters(event) {
		const url = new URL(event.target.action);

		this.reloadAndScroll(url);
	}

	reloadAndScroll(url) {
		window.location.href = `${url}${ChocanteProductFilters.SHOP_LOOP}`;
	}
}

document.addEventListener("DOMContentLoaded", () => {
	const filters = document.getElementById(ChocanteProductFilters.PRODUCT_FILTERS);

	if(filters) {
		new ChocanteProductFilters(filters);
	}
});
