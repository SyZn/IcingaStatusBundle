var IcingaStatus;
(function (IcingaStatus) {
    function matrixFloatingHeader() {
        window.addEventListener("load", performMatrixFloatingHeader);
    }
    IcingaStatus.matrixFloatingHeader = matrixFloatingHeader;
    function performMatrixFloatingHeader() {
        var firstHostNameElement = document.querySelector(".service-matrix .host-name");
        var firstHostNameElementSize = elementSize(firstHostNameElement);
        var hostHeaderElement = document.querySelector(".service-matrix .host-header");
        hostHeaderElement.style.width = firstHostNameElementSize[0] + "px";
        var headerRow = document.querySelector(".service-matrix thead tr");
        var headerRowSize = elementSize(headerRow);
        var tableHead = document.querySelector(".service-matrix thead");
        var spacerRow = document.createElement("tr");
        var spacerCell = document.createElement("td");
        spacerCell.style.height = headerRowSize[1] + "px";
        spacerRow.appendChild(spacerCell);
        tableHead.appendChild(spacerRow);
        headerRow.style.position = "fixed";
    }
    function elementSize(element) {
        var elementStyle = window.getComputedStyle(element);
        var width = element.clientWidth - (parseFloat(elementStyle.paddingLeft) + parseFloat(elementStyle.paddingRight));
        var height = element.clientHeight - (parseFloat(elementStyle.paddingTop) + parseFloat(elementStyle.paddingBottom));
        return [width, height];
    }
})(IcingaStatus || (IcingaStatus = {}));
//# sourceMappingURL=script.js.map