function withBilling(e) {
  let billing = e.checked;
  console.log(billing);
  if (billing == true) {
    document
      .getElementById("form-billing")
      .classList.add("bform-show", "fadeIn");
    document
      .getElementById("form-billing")
      .classList.remove("bform-hide", "fadeOutDown");
  } else {
    document
      .getElementById("form-billing")
      .classList.remove("bform-show", "fadeIn");
    document
      .getElementById("form-billing")
      .classList.add("bform-hide", "fadeOutDown");
  }
}
