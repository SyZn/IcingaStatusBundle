module IcingaStatus
{
    export function matrixFloatingHeader()
    {
        window.addEventListener("load", performMatrixFloatingHeader);
    }

    function performMatrixFloatingHeader()
    {
        let firstHostNameElement = <HTMLTableDataCellElement>document.querySelector(".service-matrix .host-name");
        let firstHostNameElementSize = elementSize(firstHostNameElement);

        let hostHeaderElement = <HTMLTableDataCellElement>document.querySelector(".service-matrix .host-header");
        hostHeaderElement.style.width = firstHostNameElementSize[0] + "px";

        let headerRow = <HTMLTableRowElement>document.querySelector(".service-matrix thead tr");
        let headerRowSize = elementSize(headerRow);

        let tableHead = <HTMLTableSectionElement>document.querySelector(".service-matrix thead");

        let spacerRow = document.createElement("tr");
        let spacerCell = document.createElement("td");
        spacerCell.style.height = headerRowSize[1] + "px";
        spacerRow.appendChild(spacerCell);
        tableHead.appendChild(spacerRow);

        headerRow.style.position = "fixed";
    }

    function elementSize(element: HTMLElement)
    {
        let elementStyle = window.getComputedStyle(element);
        let width = element.clientWidth - (parseFloat(elementStyle.paddingLeft) + parseFloat(elementStyle.paddingRight));
        let height = element.clientHeight - (parseFloat(elementStyle.paddingTop) + parseFloat(elementStyle.paddingBottom));
        return [width, height];
    }
}
