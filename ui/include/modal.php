<?php if(!defined('SITE_ROOT')) exit();?>
<!-- Modal example -->
    <dialog id="modal-example">
      <article>
        <header>
          <button
            aria-label="Close"
            rel="prev"
            data-target="modal-example"
            onclick="toggleModal(event)"
          ></button>
          <h3>Confirm your action!</h3>
        </header>
        <p>
          Cras sit amet maximus risus. Pellentesque sodales odio sit amet augue finibus
          pellentesque. Nullam finibus risus non semper euismod.
        </p>
        <footer>
          <button
            role="button"
            class="secondary"
            data-target="modal-example"
            onclick="toggleModal(event)"
          >
            Cancel</button
          ><button autofocus data-target="modal-example" onclick="toggleModal(event)">
            Confirm
          </button>
        </footer>
      </article>
    </dialog>
    <!-- ./ Modal example -->
