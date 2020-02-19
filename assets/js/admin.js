(function () {
	var containers = document.getElementsByClassName( 'rplus-notifications-iframe-sandbox' );
	if ( ! containers ) {
		return;
	}

	for ( var i = 0; i < containers.length; i++ ) {
		var container = containers[ i ];
		wp.element.render(
			wp.element.createElement(
				wp.components.SandBox,
				{
					html: container.dataset.content
				}
			),
			container
		);
	}
})();
